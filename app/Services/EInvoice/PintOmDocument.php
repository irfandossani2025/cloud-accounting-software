<?php

namespace App\Services\EInvoice;

use App\Enums\VatCategory;
use App\Enums\VoucherBaseType;
use App\Models\CompanySetting;
use App\Models\Ledger;
use App\Models\Voucher;
use App\Support\Money;
use App\Support\PintOm;

/**
 * The business data of a PINT OM invoice or credit note, derived from a voucher. Shared by the
 * pre-flight checks and the XML builder so both see exactly the same values.
 * Amounts are integers in thousandths of the document currency.
 */
final class PintOmDocument
{
    public bool $isCreditNote;

    public bool $isFullInvoice;

    public string $transactionType;

    public string $uuid;

    public string $currency;

    public ?string $fxRate;

    /** @var list<array> */
    public array $lines = [];

    /** @var array<string, array{category: string, percent: ?string, exemption: ?string, taxable: int, tax: int}> */
    public array $breakdown = [];

    public int $lineTotal = 0;

    public int $taxTotal = 0;

    /** VAT total in OMR as booked (differs from taxTotal only for foreign-currency invoices). */
    public int $taxTotalOmr = 0;

    public function __construct(public Voucher $voucher, public CompanySetting $company, public ?Ledger $buyer)
    {
        $voucher->loadMissing(['type', 'invoiceLines.ledger', 'invoiceLines.stockItem.unit', 'party', 'currency', 'originalVoucher.einvoice']);

        $this->isCreditNote = $voucher->type->base_type === VoucherBaseType::CreditNote;
        $this->currency = $voucher->currency?->code ?? 'OMR';
        $this->fxRate = $voucher->currency_id ? rtrim(rtrim((string) $voucher->fx_rate, '0'), '.') : null;

        // A full tax invoice needs the buyer's VATIN or another identifier (IBR-016-OM); otherwise it is simplified.
        $this->isFullInvoice = $buyer && (PintOm::validVatin($buyer->vatin) || ($buyer->party_id && $buyer->party_id_scheme));
        $this->transactionType = PintOm::transactionType($this->isFullInvoice ? PintOm::TX_FULL : PintOm::TX_SIMPLIFIED);
        $this->uuid = PintOm::uuid5($this->sellerUuid(), 'voucher:'.$voucher->id);

        foreach ($voucher->invoiceLines as $i => $line) {
            $category = $line->vat_category;
            $code = self::categoryCode($category);
            $amount = Money::toBaisa($line->amount);
            $vat = $code === 'S' ? Money::toBaisa($line->vat_amount) : 0;
            $source = $line->stockItem ?? $line->ledger;
            $exemption = in_array($code, ['Z', 'E'], true)
                ? ($line->stockItem?->exemption_code ?: $line->ledger->exemption_code)
                : null;

            $this->lines[] = [
                'id' => $i + 1,
                'line' => $line,
                'name' => $line->description,
                'description_ar' => $line->description_ar,
                'quantity' => Money::toBaisa($line->quantity),
                'unit' => self::unitCode($line->stockItem?->unit?->symbol ?? $line->unit),
                'price' => Money::toBaisa($line->rate),
                'discount' => Money::toBaisa($line->discount),
                'amount' => $amount,
                'vat' => $vat,
                'category' => $code,
                'percent' => $code === 'S' ? '5' : ($code === 'Z' ? '0' : null),
                'exemption' => $exemption,
                'item_type' => $line->stockItem?->item_type ?: $line->ledger->item_type,
                'hs_code' => $line->stockItem?->hs_code ?: $line->ledger->hs_code,
                'isic_code' => $line->stockItem?->isic_code ?: $line->ledger->isic_code,
                'source' => $source,
            ];

            $key = $code.'|'.($exemption ?? '');
            $this->breakdown[$key] ??= ['category' => $code, 'percent' => $code === 'S' ? '5' : ($code === 'Z' ? '0' : null), 'exemption' => $exemption, 'taxable' => 0, 'tax' => 0];
            $this->breakdown[$key]['taxable'] += $amount;
            $this->breakdown[$key]['tax'] += $vat;
            $this->lineTotal += $amount;
            $this->taxTotal += $vat;
        }

        // VAT in OMR as posted to the books (5% of each converted line, see InvoiceService::inOmr()).
        $this->taxTotalOmr = $this->fxRate
            ? array_sum(array_map(fn ($l) => $l['vat'] ? Money::vat(Money::convert($l['amount'], $this->fxRate), '5') : 0, $this->lines))
            : $this->taxTotal;
    }

    /** Round thousandths to whole hundredths, half up (document-level amounts use 2 decimals). */
    public static function round2(int $thousandths): int
    {
        $sign = $thousandths < 0 ? -1 : 1;

        return $sign * intdiv(abs($thousandths) + 5, 10) * 10;
    }

    /** Invoice total VAT at document level: the sum of the rounded category amounts (ibr-co-14). */
    public function documentTax(): int
    {
        return array_sum(array_map(fn ($g) => self::round2($g['tax']), $this->breakdown));
    }

    public static function forVoucher(Voucher $voucher): self
    {
        return new self($voucher, CompanySetting::current(), $voucher->party);
    }

    public function sellerUuid(): string
    {
        return $this->company->einvoice_seller_uuid ?: PintOm::sellerUuid((string) $this->company->vatin);
    }

    public function buyerEndpoint(): string
    {
        return PintOm::endpointFor($this->buyer?->vatin) ?? PintOm::UNKNOWN_BUYER_ENDPOINT;
    }

    public static function categoryCode(VatCategory $category): string
    {
        return match ($category) {
            VatCategory::Standard => 'S',
            VatCategory::ZeroRated => 'Z',
            VatCategory::Exempt => 'E',
            // Reverse charge is accounted for by the buyer: nothing is charged on this invoice.
            VatCategory::ReverseCharge, VatCategory::OutOfScope => 'O',
        };
    }

    /** UN/ECE Recommendation 20 unit code for the app's unit symbols. */
    public static function unitCode(?string $symbol): string
    {
        return match (mb_strtolower(trim((string) $symbol))) {
            'kg', 'kgs' => 'KGM',
            'g', 'gm' => 'GRM',
            'ltr', 'l', 'litre', 'liter' => 'LTR',
            'mtr', 'm', 'metre', 'meter' => 'MTR',
            'hrs', 'hr', 'hour', 'hours' => 'HUR',
            'day', 'days' => 'DAY',
            'box' => 'XBX',
            default => 'H87',
        };
    }
}
