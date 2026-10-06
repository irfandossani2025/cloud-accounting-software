<?php

namespace App\Services\Documents;

use App\Enums\VoucherBaseType;
use App\Models\CompanySetting;
use App\Models\Voucher;
use App\Services\InvoiceService;
use App\Support\Money;
use TCPDF;

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

    /** PDF bytes of an invoice or credit note (Arabic shaped by TCPDF, LGPL). */
    public function pdf(Voucher $voucher): string
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 14, 14);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->SetTitle($this->filename($voucher));
        $pdf->SetCreator(config('app.name'));
        // DejaVu Sans covers Latin and Arabic, so mixed English/Arabic lines need no font switching.
        $pdf->SetFont('dejavusans', '', 8.5);
        $pdf->AddPage();
        // TCPDF prints the template's line breaks and indentation as spaces at the start of lines.
        $html = preg_replace('/\n\s*/', '', view('pdf.invoice', $this->data($voucher))->render());
        $pdf->writeHTML($html);

        return $pdf->Output($this->filename($voucher), 'S');
    }

    public function filename(Voucher $voucher, string $extension = 'pdf'): string
    {
        return preg_replace('/[^A-Za-z0-9_-]+/', '-', $voucher->type->name.'-'.$voucher->number).'.'.$extension;
    }
}
