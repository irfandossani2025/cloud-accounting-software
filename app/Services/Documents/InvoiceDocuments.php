<?php

namespace App\Services\Documents;

use App\Enums\VoucherBaseType;
use App\Models\CompanySetting;
use App\Models\Voucher;
use App\Services\InvoiceService;
use App\Support\Money;
use Mpdf\Mpdf;

/**
 * Printable, PDF and shareable forms of a voucher. The browser print view and the PDF share the
 * same data so they cannot disagree.
 */
class InvoiceDocuments
{
    /** @return array{0: string, 1: string} English and Arabic document title. */
    public function titles(Voucher $voucher, CompanySetting $company): array
    {
        return match (true) {
            ! $voucher->is_invoice => [$voucher->type->name.' Voucher', 'سند'],
            $voucher->type->base_type === VoucherBaseType::Sales => $company->vat_registered ? ['Tax Invoice', 'فاتورة ضريبية'] : ['Invoice', 'فاتورة'],
            $voucher->type->base_type === VoucherBaseType::CreditNote => $company->vat_registered ? ['Tax Credit Note', 'إشعار دائن ضريبي'] : ['Credit Note', 'إشعار دائن'],
            $voucher->type->base_type === VoucherBaseType::DebitNote => ['Debit Note', 'إشعار مدين'],
            default => ['Purchase Invoice', 'فاتورة مشتريات'],
        };
    }

    /** View data shared by the print page and the PDF. */
    public function data(Voucher $voucher): array
    {
        $voucher->loadMissing(['type', 'party', 'invoiceLines', 'entries.ledger', 'currency']);
        $company = CompanySetting::current();
        [$title, $titleAr] = $this->titles($voucher, $company);

        $net = $voucher->invoiceLines->sum(fn ($l) => Money::toBaisa($l->amount));
        $vat = $voucher->invoiceLines->reject(fn ($l) => $l->vat_category === \App\Enums\VatCategory::ReverseCharge)->sum(fn ($l) => Money::toBaisa($l->vat_amount));
        $books = $voucher->currency_id ? app(InvoiceService::class)->inOmr($voucher->invoiceLines->map(fn ($l) => [
            'ledger_id' => $l->ledger_id, 'amount' => $l->amount, 'vat_amount' => $l->vat_amount, 'vat_rate' => $l->vat_rate,
            'vat_category' => $l->vat_category, 'cost_centre_id' => null,
        ])->all(), $voucher->fx_rate) : null;

        return compact('voucher', 'company', 'title', 'titleAr', 'net', 'vat', 'books') + ['code' => $voucher->currency?->code ?? 'OMR'];
    }

    /** PDF bytes of an invoice or credit note (Arabic shaped by mPDF). */
    public function pdf(Voucher $voucher): string
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 14, 'margin_right' => 14, 'margin_top' => 14, 'margin_bottom' => 14,
            'tempDir' => storage_path('app/mpdf'),
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $mpdf->SetTitle($this->filename($voucher));
        $mpdf->WriteHTML(view('pdf.invoice', $this->data($voucher))->render());

        return $mpdf->Output('', 'S');
    }

    public function filename(Voucher $voucher, string $extension = 'pdf'): string
    {
        return preg_replace('/[^A-Za-z0-9_-]+/', '-', $voucher->type->name.'-'.$voucher->number).'.'.$extension;
    }
}
