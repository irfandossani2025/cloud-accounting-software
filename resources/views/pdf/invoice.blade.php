@php
    use App\Support\Money;
    $party = $voucher->party;
    $isSale = in_array($voucher->type->base_type->value, ['sales', 'credit_note'], true);
@endphp
<html>
<head>
<style>
    body { font-family: sans-serif; font-size: 9.5pt; color: #111827; }
    .ar { direction: rtl; text-align: right; }
    .muted { color: #6b7280; }
    table { border-collapse: collapse; width: 100%; }
    .lines th { background: #e4eef1; color: #1d5b6b; font-size: 8pt; text-align: left; padding: 5px 4px; border-bottom: 1px solid #c9d8dd; }
    .lines td { padding: 5px 4px; border-bottom: 0.5px solid #e5e7eb; vertical-align: top; }
    .num, .lines th.num { text-align: right; white-space: nowrap; }
    .box { border: 0.5px solid #c9d8dd; padding: 6px; }
    .totals td { padding: 3px 4px; }
    .grand td { border-top: 1.5px solid #111827; font-weight: bold; font-size: 11pt; }
</style>
</head>
<body>
    <table style="border-bottom: 2px solid #1d5b6b; margin-bottom: 8px;">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-bottom: 6px;">
                <div style="font-size: 15pt; font-weight: bold;">{{ $company->name }}</div>
                <div class="muted">{{ collect([$company->street, $company->additional_street, $company->po_box, $company->city.' '.$company->postal_code])->filter()->implode(', ') ?: $company->address }}</div>
                @if ($company->vatin)<div>VATIN: <b>{{ $company->vatin }}</b></div>@endif
                @if ($company->cr_number)<div>CR No: {{ $company->cr_number }}</div>@endif
                @if ($company->phone || $company->email)<div class="muted">{{ collect([$company->phone, $company->email])->filter()->implode(' · ') }}</div>@endif
            </td>
            <td class="ar" style="width: 50%; vertical-align: top; padding-bottom: 6px;">
                @if ($company->name_ar)<div style="font-size: 15pt; font-weight: bold;">{{ $company->name_ar }}</div>@endif
                @if ($company->address_ar)<div class="muted">{{ $company->address_ar }}</div>@endif
                @if ($company->vatin)<div>الرقم الضريبي: <b>{{ $company->vatin }}</b></div>@endif
                @if ($company->cr_number)<div>السجل التجاري: {{ $company->cr_number }}</div>@endif
            </td>
        </tr>
    </table>

    <table style="margin-bottom: 8px;">
        <tr>
            <td style="font-size: 14pt; font-weight: bold;">{{ $title }}</td>
            <td class="ar" style="font-size: 15pt; font-weight: bold;">{{ $titleAr }}</td>
        </tr>
    </table>

    <table style="margin-bottom: 10px;">
        <tr>
            <td class="box" style="width: 55%; vertical-align: top;">
                <div class="muted">{{ $isSale ? 'Bill to / العميل' : 'Supplier / المورد' }}</div>
                <div style="font-size: 11pt; font-weight: bold;">{{ $party?->name }}</div>
                @if ($party?->name_ar)<div class="ar">{{ $party->name_ar }}</div>@endif
                <div class="muted">{{ collect([$party?->street, $party?->additional_street, $party?->po_box, trim(($party?->city ?? '').' '.($party?->postal_code ?? ''))])->filter()->implode(', ') ?: $party?->address }}</div>
                @if ($party?->vatin)<div>VATIN / الرقم الضريبي: <b>{{ $party->vatin }}</b></div>@endif
            </td>
            <td style="width: 3%;"></td>
            <td class="box" style="width: 42%; vertical-align: top;">
                <table class="totals">
                    <tr><td class="muted">Number / الرقم</td><td class="num"><b>{{ $voucher->number }}</b></td></tr>
                    <tr><td class="muted">Date / التاريخ</td><td class="num">{{ $voucher->date->format('d/m/Y') }}</td></tr>
                    @if ($voucher->reference)<tr><td class="muted">Reference / المرجع</td><td class="num">{{ $voucher->reference }}</td></tr>@endif
                    @if ($voucher->due_date)<tr><td class="muted">Due / الاستحقاق</td><td class="num">{{ $voucher->due_date->format('d/m/Y') }}</td></tr>@endif
                    @if ($books)<tr><td class="muted">Currency / العملة</td><td class="num">{{ $code }} @ {{ rtrim(rtrim($voucher->fx_rate, '0'), '.') }}</td></tr>@endif
                </table>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>#</th>
                <th>Description<br><span class="ar">الوصف</span></th>
                <th class="num">Qty<br>الكمية</th>
                <th class="num">Unit price<br>سعر الوحدة</th>
                <th class="num">Discount<br>الخصم</th>
                <th class="num">Taxable<br>الخاضع</th>
                <th class="num">VAT %<br>النسبة</th>
                <th class="num">VAT<br>الضريبة</th>
                <th class="num">Total<br>الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($voucher->invoiceLines as $i => $line)
                @php $lineVat = $line->vat_category === \App\Enums\VatCategory::ReverseCharge ? 0 : Money::toBaisa($line->vat_amount); @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->description }}@if ($line->description_ar)<div class="ar">{{ $line->description_ar }}</div>@endif</td>
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

    <table style="margin-top: 10px;">
        <tr>
            <td style="width: 52%; vertical-align: top;">
                <div class="muted">Amount in words</div>
                <div><b>{{ $books ? $code.' '.Money::format($net + $vat).' — OMR '.Money::format($books['total']).' ('.Money::inWords($books['total']).')' : Money::inWords($net + $vat) }}</b></div>
                @if ($voucher->narration)<div class="muted" style="margin-top: 6px;">{{ $voucher->narration }}</div>@endif
            </td>
            <td style="width: 48%; vertical-align: top;">
                <table class="totals">
                    <tr><td>Total excluding VAT</td><td class="ar muted">الإجمالي غير شامل الضريبة</td><td class="num">{{ Money::format($net) }}</td></tr>
                    <tr><td>VAT</td><td class="ar muted">ضريبة القيمة المضافة</td><td class="num">{{ Money::format($vat) }}</td></tr>
                    <tr class="grand"><td>Total ({{ $code }})</td><td class="ar">الإجمالي</td><td class="num">{{ Money::format($net + $vat) }}</td></tr>
                    @if ($books)
                        <tr><td>Taxable (OMR)</td><td class="ar muted">المبلغ الخاضع</td><td class="num">{{ Money::format($books['net']) }}</td></tr>
                        <tr><td>VAT (OMR)</td><td class="ar muted">الضريبة</td><td class="num">{{ Money::format($books['vat']) }}</td></tr>
                        <tr><td><b>Total (OMR)</b></td><td class="ar"><b>الإجمالي (ر.ع.)</b></td><td class="num"><b>{{ Money::format($books['total']) }}</b></td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
