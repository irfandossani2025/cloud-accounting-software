<?php

namespace App\Http\Controllers;

use App\Enums\VoucherBaseType;
use App\Models\CompanySetting;
use App\Models\Voucher;

class VoucherPrintController extends Controller
{
    public function __invoke(Voucher $voucher)
    {
        abort_if($voucher->is_cancelled, 404);

        $voucher->load(['type', 'party', 'invoiceLines', 'entries.ledger']);
        $company = CompanySetting::current();

        [$title, $titleAr] = match (true) {
            ! $voucher->is_invoice => [$voucher->type->name.' Voucher', 'سند'],
            $voucher->type->base_type === VoucherBaseType::Sales => $company->vat_registered ? ['Tax Invoice', 'فاتورة ضريبية'] : ['Invoice', 'فاتورة'],
            $voucher->type->base_type === VoucherBaseType::CreditNote => $company->vat_registered ? ['Tax Credit Note', 'إشعار دائن ضريبي'] : ['Credit Note', 'إشعار دائن'],
            $voucher->type->base_type === VoucherBaseType::DebitNote => ['Debit Note', 'إشعار مدين'],
            default => ['Purchase Invoice', 'فاتورة مشتريات'],
        };

        return view($voucher->is_invoice ? 'print.invoice' : 'print.voucher', compact('voucher', 'company', 'title', 'titleAr'));
    }
}
