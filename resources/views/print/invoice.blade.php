@php
    use App\Support\Money;
    $net = $voucher->invoiceLines->sum(fn ($l) => Money::toBaisa($l->amount));
    $vat = $voucher->invoiceLines->reject(fn ($l) => $l->vat_category === \App\Enums\VatCategory::ReverseCharge)->sum(fn ($l) => Money::toBaisa($l->vat_amount));
    $party = $voucher->party;
    $code = $voucher->currency?->code ?? 'OMR';
    $books = $voucher->currency_id ? app(\App\Services\InvoiceService::class)->inOmr($voucher->invoiceLines->map(fn ($l) => [
        'ledger_id' => $l->ledger_id, 'amount' => $l->amount, 'vat_amount' => $l->vat_amount, 'vat_rate' => $l->vat_rate,
        'vat_category' => $l->vat_category, 'cost_centre_id' => null,
    ])->all(), $voucher->fx_rate) : null;
@endphp
<x-print-layout :title="$title" :title-ar="$titleAr" :voucher="$voucher" :company="$company">
    <div class="row" style="margin-bottom: 12px;">
        <div class="box" style="flex: 1">
            <div class="row"><span class="muted">{{ in_array($voucher->type->base_type->value, ['sales', 'credit_note']) ? 'Bill to' : 'Supplier' }}</span><span class="ar muted">{{ in_array($voucher->type->base_type->value, ['sales', 'credit_note']) ? 'العميل' : 'المورد' }}</span></div>
            <div style="font-size: 14px; font-weight: 700">{{ $party?->name }}</div>
            @if ($party?->name_ar)<div class="ar">{{ $party->name_ar }}</div>@endif
            @if ($party?->address)<div class="muted" style="white-space: pre-line">{{ $party->address }}</div>@endif
            @if ($party?->vatin)<div>VATIN / الرقم الضريبي: <strong>{{ $party->vatin }}</strong></div>@endif
            @if ($party?->cr_number)<div>CR No: {{ $party->cr_number }}</div>@endif
        </div>
        <div class="box" style="width: 45%">
            <table class="totals">
                <tr><td class="muted">{{ $voucher->type->base_type->value === 'sales' ? 'Invoice no.' : 'Document no.' }}</td><td><strong>{{ $voucher->number }}</strong></td><td class="ar muted">رقم المستند</td></tr>
                <tr><td class="muted">Date</td><td>{{ $voucher->date->format('d/m/Y') }}</td><td class="ar muted">التاريخ</td></tr>
                @if ($voucher->reference)<tr><td class="muted">Reference</td><td>{{ $voucher->reference }}</td><td class="ar muted">المرجع</td></tr>@endif
                @if ($books)<tr><td class="muted">Currency</td><td>{{ $code }} @ {{ rtrim(rtrim($voucher->fx_rate, '0'), '.') }}</td><td class="ar muted">العملة</td></tr>@endif
                @if ($voucher->due_date)<tr><td class="muted">Due date</td><td>{{ $voucher->due_date->format('d/m/Y') }}</td><td class="ar muted">تاريخ الاستحقاق</td></tr>@endif
            </table>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Description <span class="ar">الوصف</span></th>
                <th class="num">Qty <span class="ar">الكمية</span></th>
                <th class="num">Unit price <span class="ar">سعر الوحدة</span></th>
                <th class="num">Discount <span class="ar">الخصم</span></th>
                <th class="num">Taxable amount <span class="ar">المبلغ الخاضع</span></th>
                <th class="num">VAT % <span class="ar">نسبة الضريبة</span></th>
                <th class="num">VAT <span class="ar">الضريبة</span></th>
                <th class="num">Total <span class="ar">الإجمالي</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($voucher->invoiceLines as $i => $line)
                @php $lineVat = $line->vat_category === \App\Enums\VatCategory::ReverseCharge ? 0 : Money::toBaisa($line->vat_amount); @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $line->description }}
                        @if ($line->description_ar)<div class="ar">{{ $line->description_ar }}</div>@endif
                        @if (! in_array($line->vat_category->value, ['standard']))<div class="muted" style="font-size: 10px">{{ $line->vat_category->label() }}</div>@endif
                    </td>
                    <td class="num">{{ rtrim(rtrim($line->quantity, '0'), '.') }} {{ $line->unit }}</td>
                    <td class="num">{{ Money::format($line->rate) }}</td>
                    <td class="num">{{ Money::format($line->discount, true) }}</td>
                    <td class="num">{{ Money::format($line->amount) }}</td>
                    <td class="num">{{ rtrim(rtrim($line->vat_rate, '0'), '.') }}%</td>
                    <td class="num">{{ Money::format($lineVat) }}</td>
                    <td class="num">{{ Money::format(Money::toBaisa($line->amount) + $lineVat) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="row" style="margin-top: 12px; align-items: flex-start">
        <div style="flex: 1">
            <div class="muted">Amount in words</div>
            <div><strong>{{ $books ? $code.' '.Money::format($net + $vat).' — OMR '.Money::format($books['total']).' ('.Money::inWords($books['total']).')' : Money::inWords($net + $vat) }}</strong></div>
            @if ($voucher->narration)<p class="muted" style="white-space: pre-line">{{ $voucher->narration }}</p>@endif
        </div>
        <table class="totals" style="width: 45%">
            <tr><td>Total excluding VAT</td><td class="ar muted">الإجمالي غير شامل الضريبة</td><td class="num">{{ Money::format($net) }}</td></tr>
            <tr><td>VAT</td><td class="ar muted">ضريبة القيمة المضافة</td><td class="num">{{ Money::format($vat) }}</td></tr>
            <tr class="grand"><td>Total ({{ $code }})</td><td class="ar">الإجمالي</td><td class="num">{{ Money::format($net + $vat) }}</td></tr>
            @if ($books)
                <tr><td colspan="3" style="padding-top: 10px" class="muted">Equivalent in Omani Rials / المعادل بالريال العماني</td></tr>
                <tr><td>Taxable amount (OMR)</td><td class="ar muted">المبلغ الخاضع</td><td class="num">{{ Money::format($books['net']) }}</td></tr>
                <tr><td>VAT (OMR)</td><td class="ar muted">الضريبة</td><td class="num">{{ Money::format($books['vat']) }}</td></tr>
                <tr><td><strong>Total (OMR)</strong></td><td class="ar">الإجمالي (ر.ع.)</td><td class="num"><strong>{{ Money::format($books['total']) }}</strong></td></tr>
            @endif
        </table>
    </div>

    <div class="row" style="margin-top: 48px">
        <div style="border-top: 1px solid #9ca3af; width: 40%; padding-top: 4px" class="muted">Received by <span class="ar">المستلم</span></div>
        <div style="border-top: 1px solid #9ca3af; width: 40%; padding-top: 4px; text-align: right" class="muted">For {{ $company->name }}</div>
    </div>
</x-print-layout>
