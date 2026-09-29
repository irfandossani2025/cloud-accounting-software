<?php

namespace App\Services\EInvoice;

use App\Support\Money;
use App\Support\PintOm;
use DOMDocument;
use DOMElement;

/**
 * Builds a UBL 2.1 Invoice / CreditNote conforming to Peppol PINT OM Billing 1.0.1.
 */
final class PintOmXmlBuilder
{
    private const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private DOMDocument $dom;

    public function build(PintOmDocument $doc): string
    {
        $voucher = $doc->voucher;
        $root = $doc->isCreditNote ? 'CreditNote' : 'Invoice';

        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;
        $x = $this->dom->createElementNS("urn:oasis:names:specification:ubl:schema:xsd:{$root}-2", $root);
        $x->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::CAC);
        $x->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::CBC);
        $this->dom->appendChild($x);

        $this->cbc($x, 'CustomizationID', PintOm::CUSTOMIZATION_ID);
        $this->cbc($x, 'ProfileID', PintOm::PROFILE_ID);
        $this->cbc($x, 'ID', $voucher->number);
        $this->cbc($x, 'UUID', $doc->uuid);
        $this->cbc($x, 'IssueDate', $voucher->date->toDateString());
        $this->cbc($x, 'IssueTime', ($voucher->created_at ?? now())->copy()->setTimezone('Asia/Muscat')->format('H:i:s'));
        if (! $doc->isCreditNote && $voucher->due_date) {
            $this->cbc($x, 'DueDate', $voucher->due_date->toDateString());
        }
        $this->cbc($x, $doc->isCreditNote ? 'CreditNoteTypeCode' : 'InvoiceTypeCode', $doc->isCreditNote ? '381' : '380', ['name' => $doc->transactionType]);
        if ($voucher->narration) {
            $this->cbc($x, 'Note', $voucher->narration);
        }
        $this->cbc($x, 'DocumentCurrencyCode', $doc->currency);
        if ($doc->fxRate) {
            $this->cbc($x, 'TaxCurrencyCode', 'OMR');
        }
        if ($voucher->reference) {
            $this->cbc($x, 'BuyerReference', $voucher->reference);
        }

        if ($doc->isCreditNote && $original = $voucher->originalVoucher) {
            $ref = $this->cac($this->cac($x, 'BillingReference'), 'InvoiceDocumentReference');
            $this->cbc($ref, 'ID', $original->number);
            // Invoices issued before e-invoicing are referenced with the nil UUID.
            $this->cbc($ref, 'UUID', $original->einvoice?->uuid ?? '00000000-0000-0000-0000-000000000000');
            $this->cbc($ref, 'IssueDate', $original->date->toDateString());
            $this->cbc($ref, 'DocumentStatusCode', $voucher->issuance_reason);
        }

        $this->supplier($x, $doc);
        $this->customer($x, $doc);

        if ($doc->fxRate) {
            $rate = $this->cac($x, 'TaxExchangeRate');
            $this->cbc($rate, 'SourceCurrencyCode', $doc->currency);
            $this->cbc($rate, 'TargetCurrencyCode', 'OMR');
            $this->cbc($rate, 'CalculationRate', $doc->fxRate);
        }

        // Document-level amounts carry at most 2 decimals (PINT ibr-123..125, ibr-091); lines keep baisa.
        $taxTotal = $this->cac($x, 'TaxTotal');
        $this->amount($taxTotal, 'TaxAmount', $doc->documentTax(), $doc->currency, 2);
        // Foreign-currency invoices carry the category breakdown only in the OMR tax total: PINT OM 1.0.1 requires
        // an OMR breakdown (IBR-066-OM) but allows exactly one "S" breakdown per rate overall (ALIGNED-IBRP-S-01-OM).
        foreach ($doc->fxRate ? [] : $doc->breakdown as $group) {
            $sub = $this->cac($taxTotal, 'TaxSubtotal');
            $this->amount($sub, 'TaxableAmount', PintOmDocument::round2($group['taxable']), $doc->currency, 2);
            $this->amount($sub, 'TaxAmount', PintOmDocument::round2($group['tax']), $doc->currency, 2);
            $this->taxCategory($this->cac($sub, 'TaxCategory'), $group['category'], $group['percent'], $group['exemption']);
        }
        if ($doc->fxRate) {
            // VAT in OMR (IBT-111) = exchange rate x invoice VAT (IBR-065-OM), with an OMR breakdown per category (IBR-066-OM).
            $omr = $this->cac($x, 'TaxTotal');
            $this->amount($omr, 'TaxAmount', PintOmDocument::round2(Money::convert($doc->documentTax(), $doc->fxRate)), 'OMR', 2);
            foreach ($doc->breakdown as $group) {
                $sub = $this->cac($omr, 'TaxSubtotal');
                $this->amount($sub, 'TaxableAmount', PintOmDocument::round2(Money::convert($group['taxable'], $doc->fxRate)), 'OMR', 2);
                $this->amount($sub, 'TaxAmount', PintOmDocument::round2(Money::convert(PintOmDocument::round2($group['tax']), $doc->fxRate)), 'OMR', 2);
                $this->taxCategory($this->cac($sub, 'TaxCategory'), $group['category'], $group['percent'], $group['exemption']);
            }
        }

        $totals = $this->cac($x, 'LegalMonetaryTotal');
        $net = PintOmDocument::round2($doc->lineTotal);
        $gross = $net + $doc->documentTax();
        $this->amount($totals, 'LineExtensionAmount', $net, $doc->currency, 2);
        $this->amount($totals, 'TaxExclusiveAmount', $net, $doc->currency, 2);
        $this->amount($totals, 'TaxInclusiveAmount', $gross, $doc->currency, 2);
        $this->amount($totals, 'PayableAmount', $gross, $doc->currency, 2);

        foreach ($doc->lines as $line) {
            $this->line($x, $doc, $line);
        }

        return $this->dom->saveXML();
    }

    private function supplier(DOMElement $x, PintOmDocument $doc): void
    {
        $company = $doc->company;
        $party = $this->cac($x, 'AccountingSupplierParty');
        $this->cbc($party, 'AdditionalAccountID', $doc->sellerUuid());
        $p = $this->cac($party, 'Party');
        $this->cbc($p, 'EndpointID', (string) PintOm::endpointFor($company->vatin), ['schemeID' => PintOm::ENDPOINT_SCHEME]);
        if ($company->cr_number) {
            $this->cbc($this->cac($p, 'PartyIdentification'), 'ID', $company->cr_number, ['schemeName' => 'CR']);
        }
        $this->cbc($this->cac($p, 'PartyName'), 'Name', $company->name);
        $this->address($this->cac($p, 'PostalAddress'), $company->street, $company->additional_street, $company->po_box, $company->city, $company->postal_code, $company->country_subdivision, 'OM');
        $this->vatScheme($p, (string) $company->vatin);
        $legal = $this->cac($p, 'PartyLegalEntity');
        $this->cbc($legal, 'RegistrationName', $company->name);
        $contact = $this->cac($p, 'Contact');
        $this->cbc($contact, 'Telephone', (string) $company->phone);
        if ($company->email) {
            $this->cbc($contact, 'ElectronicMail', $company->email);
        }
    }

    private function customer(DOMElement $x, PintOmDocument $doc): void
    {
        $buyer = $doc->buyer;
        $p = $this->cac($this->cac($x, 'AccountingCustomerParty'), 'Party');
        $this->cbc($p, 'EndpointID', $doc->buyerEndpoint(), ['schemeID' => PintOm::ENDPOINT_SCHEME]);
        if ($buyer?->party_id && $buyer->party_id_scheme) {
            $this->cbc($this->cac($p, 'PartyIdentification'), 'ID', $buyer->party_id, ['schemeName' => $buyer->party_id_scheme]);
        }
        $this->cbc($this->cac($p, 'PartyName'), 'Name', $buyer?->name ?? 'Cash customer');
        // The buyer postal address is always required (ibr-010); for walk-in customers the country is enough.
        $country = $buyer?->country_code ?: 'OM';
        $this->address($this->cac($p, 'PostalAddress'), $buyer?->street, $buyer?->additional_street, $buyer?->po_box, $buyer?->city, $buyer?->postal_code,
            $country === 'OM' ? ($buyer?->country_subdivision ?: 'MO') : null, $country);
        if ($buyer && PintOm::validVatin($buyer->vatin)) {
            $this->vatScheme($p, $buyer->vatin);
        }
        $this->cbc($this->cac($p, 'PartyLegalEntity'), 'RegistrationName', $buyer?->name ?? 'Cash customer');
        if ($buyer?->phone || $buyer?->email) {
            $contact = $this->cac($p, 'Contact');
            if ($buyer->phone) {
                $this->cbc($contact, 'Telephone', $buyer->phone);
            }
            if ($buyer->email) {
                $this->cbc($contact, 'ElectronicMail', $buyer->email);
            }
        }
    }

    private function line(DOMElement $x, PintOmDocument $doc, array $line): void
    {
        $cn = $doc->isCreditNote;
        $l = $this->cac($x, $cn ? 'CreditNoteLine' : 'InvoiceLine');
        $this->cbc($l, 'ID', (string) $line['id']);
        $this->cbc($l, $cn ? 'CreditedQuantity' : 'InvoicedQuantity', $this->decimal($line['quantity']), ['unitCode' => $line['unit']]);
        $this->amount($l, 'LineExtensionAmount', $line['amount'], $doc->currency);

        if ($line['discount'] > 0) {
            $ac = $this->cac($l, 'AllowanceCharge');
            $this->cbc($ac, 'ChargeIndicator', 'false');
            $this->cbc($ac, 'AllowanceChargeReasonCode', '95');
            $this->cbc($ac, 'AllowanceChargeReason', 'Discount');
            $this->amount($ac, 'Amount', $line['discount'], $doc->currency);
        }

        $item = $this->cac($l, 'Item');
        if ($line['description_ar']) {
            $this->cbc($item, 'Description', $line['description_ar']);
        }
        $this->cbc($item, 'Name', $line['name']);
        if ($line['isic_code']) {
            $this->cbc($this->cac($item, 'AdditionalItemIdentification'), 'ID', $line['isic_code'], ['schemeName' => 'CC']);
        }
        if ($line['item_type']) {
            $spec = $this->cac($item, 'ItemSpecificationDocumentReference');
            // Service type classification; 00000000 = Other.
            $this->cbc($spec, 'ID', '00000000', ['schemeName' => 'MP']);
            $this->cbc($spec, 'DocumentTypeCode', $line['item_type']);
        }
        if ($line['item_type'] === 'G' && $line['hs_code']) {
            $this->cbc($this->cac($item, 'CommodityClassification'), 'ItemClassificationCode', $line['hs_code'], ['listID' => 'HS']);
        }
        $this->taxCategory($this->cac($item, 'ClassifiedTaxCategory'), $line['category'], $line['percent'], null);

        $price = $this->cac($l, 'Price');
        $this->amount($price, 'PriceAmount', $line['price'], $doc->currency);
        $this->cbc($price, 'BaseQuantity', '1', ['unitCode' => $line['unit']]);

        // Line total including VAT (BTOM-017) and line VAT (BTOM-016).
        $ext = $this->cac($l, 'ItemPriceExtension');
        $this->amount($ext, 'Amount', $line['amount'] + $line['vat'], $doc->currency);
        $this->amount($this->cac($ext, 'TaxTotal'), 'TaxAmount', $line['vat'], $doc->currency);
    }

    private function taxCategory(DOMElement $el, string $code, ?string $percent, ?string $exemption): void
    {
        $this->cbc($el, 'ID', $code);
        if ($percent !== null) {
            $this->cbc($el, 'Percent', $percent);
        }
        if ($exemption) {
            $this->cbc($el, 'TaxExemptionReasonCode', $exemption);
        }
        $this->cbc($this->cac($el, 'TaxScheme'), 'ID', 'VAT');
    }

    private function vatScheme(DOMElement $party, string $vatin): void
    {
        $scheme = $this->cac($party, 'PartyTaxScheme');
        $this->cbc($scheme, 'CompanyID', $vatin);
        $this->cbc($this->cac($scheme, 'TaxScheme'), 'ID', 'VAT');
    }

    private function address(DOMElement $el, ?string $street, ?string $additional, ?string $line3, ?string $city, ?string $postal, ?string $subdivision, string $country): void
    {
        if ($street) {
            $this->cbc($el, 'StreetName', $street);
        }
        if ($additional) {
            $this->cbc($el, 'AdditionalStreetName', $additional);
        }
        if ($city) {
            $this->cbc($el, 'CityName', $city);
        }
        if ($postal) {
            $this->cbc($el, 'PostalZone', $postal);
        }
        if ($subdivision) {
            $this->cbc($el, 'CountrySubentityCode', $subdivision);
        }
        if ($line3) {
            $this->cbc($this->cac($el, 'AddressLine'), 'Line', $line3);
        }
        $this->cbc($this->cac($el, 'Country'), 'IdentificationCode', $country);
    }

    private function amount(DOMElement $parent, string $name, int $thousandths, string $currency, int $decimals = 3): void
    {
        $value = $this->decimal($thousandths);
        $this->cbc($parent, $name, $decimals === 2 ? substr($value, 0, -1) : $value, ['currencyID' => $currency]);
    }

    /** Thousandths to a plain decimal with up to three places (PINT OM allows at most three). */
    private function decimal(int $thousandths): string
    {
        return Money::toDecimal($thousandths);
    }

    private function cbc(DOMElement $parent, string $name, string $value, array $attributes = []): DOMElement
    {
        $el = $this->dom->createElementNS(self::CBC, 'cbc:'.$name);
        $el->appendChild($this->dom->createTextNode($value));
        foreach ($attributes as $key => $val) {
            $el->setAttribute($key, $val);
        }
        $parent->appendChild($el);

        return $el;
    }

    private function cac(DOMElement $parent, string $name): DOMElement
    {
        $el = $this->dom->createElementNS(self::CAC, 'cac:'.$name);
        $parent->appendChild($el);

        return $el;
    }
}
