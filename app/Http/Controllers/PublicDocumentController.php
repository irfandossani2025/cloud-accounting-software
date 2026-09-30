<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Ledger;
use App\Models\Voucher;
use App\Services\Documents\InvoiceDocuments;
use App\Services\OutstandingService;
use App\Services\ReportService;
use Illuminate\Support\Facades\URL;

/**
 * Pages opened by customers from emailed / WhatsApp links. Access is by signed, expiring URL only.
 */
class PublicDocumentController extends Controller
{
    public function invoice(Voucher $voucher, InvoiceDocuments $documents)
    {
        abort_if($voucher->is_cancelled || ! $voucher->is_invoice, 404);

        return view('print.invoice', $documents->data($voucher) + [
            'pdfUrl' => URL::temporarySignedRoute('public.invoice.pdf', now()->addHour(), ['voucher' => $voucher->id]),
        ]);
    }

    public function invoicePdf(Voucher $voucher, InvoiceDocuments $documents)
    {
        abort_if($voucher->is_cancelled || ! $voucher->is_invoice, 404);

        return response($documents->pdf($voucher), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$documents->filename($voucher).'"',
        ]);
    }

    public function statement(Ledger $ledger, OutstandingService $outstanding, ReportService $reports)
    {
        $today = now()->startOfDay();
        $balance = $reports->ledgerBalances($reports->financialYearStart($today), $today)->get($ledger->id)?->closing ?? 0;

        return view('public.statement', [
            'company' => CompanySetting::current(),
            'ledger' => $ledger,
            'bills' => $ledger->is_bill_wise ? $outstanding->pendingBills($ledger, $today)->filter(fn ($b) => $b->pending !== 0)->values() : collect(),
            'balance' => $balance,
            'today' => $today,
        ]);
    }
}
