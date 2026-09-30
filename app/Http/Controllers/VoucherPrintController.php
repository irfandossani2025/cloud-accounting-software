<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Services\Documents\InvoiceDocuments;

class VoucherPrintController extends Controller
{
    public function __invoke(Voucher $voucher, InvoiceDocuments $documents)
    {
        abort_if($voucher->is_cancelled, 404);

        $data = $documents->data($voucher);

        return view($voucher->is_invoice ? 'print.invoice' : 'print.voucher', $data);
    }
}
