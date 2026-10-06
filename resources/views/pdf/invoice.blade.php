@php
    use App\Support\Money;
    $party = $voucher->party;
    $isSale = in_array($voucher->type->base_type->value, ['sales', 'credit_note'], true);
@endphp
{{-- Rendered by TCPDF: tables, widths and cellpadding instead of CSS layout; dir="rtl" for Arabic blocks. --}}
<style>
    .muted { color: #6b7280; }
    .num { text-align: right; }
    .ar { text-align: right; }
    .lines th { background-color: #e4eef1; color: #1d5b6b; font-size: 7pt; font-weight: bold; border-bottom: 1px solid #c9d8dd; }
    .lines td { border-bottom: 0.5px solid #e5e7eb; }
    .box { border: 0.5px solid #c9d8dd; }
    .grand td { border-top: 1.5px solid #111827; font-weight: bold; font-size: 10pt; }
</style>

<table cellpadding="0" cellspacing="0" style="border-bottom: 2px solid #1d5b6b;">
    <tr>
        <td width="50%">
            <span style="font-size: 14pt; font-weight: bold;">{{ $company->name }}</span><br>
            <span class="muted">{{ collect([$company->street, $company->additional_street, $company->po_box, $company->city.' '.$company->postal_code])->filter()->implode(', ') ?: $company->address }}</span>
            @if ($company->vatin)<br>VATIN: <b>{{ $company->vatin }}</b>@endif
            @if ($company->cr_number)<br>CR No: {{ $company->cr_number }}@endif
            @if ($company->phone || $company->email)<br><span class="muted">{{ collect([$company->phone, $company->email])->filter()->implode(' · ') }}</span>@endif
            <br>
        </td>
        <td width="50%" dir="rtl" class="ar">
            @if ($company->name_ar)<span style="font-size: 14pt; font-weight: bold;">{{ $company->name_ar }}</span><br>@endif
            @if ($company->address_ar)<span class="muted">{{ $company->address_ar }}</span><br>@endif
            @if ($company->vatin)الرقم الضريبي: {{ $company->vatin }}<br>@endif
            @if ($company->cr_number)السجل التجاري: {{ $company->cr_number }}@endif
        </td>
    </tr>
</table>
<br><br>
<table cellpadding="0" cellspacing="0">
    <tr>
        <td width="50%" style="font-size: 13pt; font-weight: bold;">{{ $title }}</td>
        <td width="50%" dir="rtl" class="ar" style="font-size: 14pt; font-weight: bold;">{{ $titleAr }}</td>
    </tr>
</table>
<br><br>
<table cellpadding="0" cellspacing="0">
    <tr>
        <td width="55%">
            <table cellpadding="5" cellspacing="0" class="box"><tr><td>
                <span class="muted">{{ $isSale ? 'Bill to / العميل' : 'Supplier / المورد' }}</span><br>
                <span style="font-size: 10pt; font-weight: bold;">{{ $party?->name }}</span>
                @if ($party?->name_ar)<br><span dir="rtl">{{ $party->name_ar }}</span>@endif
                <br><span class="muted">{{ collect([$party?->street, $party?->additional_street, $party?->po_box, trim(($party?->city ?? '').' '.($party?->postal_code ?? ''))])->filter()->implode(', ') ?: $party?->address }}</span>
                @if ($party?->vatin)<br>VATIN / الرقم الضريبي: <b>{{ $party->vatin }}</b>@endif
            </td></tr></table>
        </td>
        <td width="3%"></td>
        <td width="42%">
            <table cellpadding="3" cellspacing="0" class="box">
                <tr><td width="55%" class="muted">Number / الرقم</td><td width="45%" class="num"><b>{{ $voucher->number }}</b></td></tr>
                <tr><td class="muted">Date / التاريخ</td><td class="num">{{ $voucher->date->format('d/m/Y') }}</td></tr>
                @if ($voucher->reference)<tr><td class="muted">Reference / المرجع</td><td class="num">{{ $voucher->reference }}</td></tr>@endif
                @if ($voucher->due_date)<tr><td class="muted">Due / الاستحقاق</td><td class="num">{{ $voucher->due_date->format('d/m/Y') }}</td></tr>@endif
                @if ($books)<tr><td class="muted">Currency / العملة</td><td class="num">{{ $code }} @ {{ rtrim(rtrim($voucher->fx_rate, '0'), '.') }}</td></tr>@endif
            </table>
        </td>
    </tr>
</table>
<br><br>
<table class="lines" cellpadding="4" cellspacing="0">
    <thead>
        <tr>
            <th width="4%">#</th>
            <th width="28%">Description<br>الوصف</th>
            <th width="9%" class="num">Qty<br>الكمية</th>
            <th width="10%" class="num">Unit price<br>سعر الوحدة</th>
            <th width="9%" class="num">Discount<br>الخصم</th>
            <th width="10%" class="num">Taxable<br>الخاضع</th>
            <th width="7%" class="num">VAT %<br>النسبة</th>
            <th width="10%" class="num">VAT<br>الضريبة</th>
            <th width="13%" class="num">Total<br>الإجمالي</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($voucher->invoiceLines as $i => $line)
            @php $lineVat = $line->vat_category === \App\Enums\VatCategory::ReverseCharge ? 0 : Money::toBaisa($line->vat_amount); @endphp
            <tr nobr="true">
                <td width="4%">{{ $i + 1 }}</td>
                <td width="28%">{{ $line->description }}@if ($line->description_ar)<br><span dir="rtl">{{ $line->description_ar }}</span>@endif</td>
                <td width="9%" class="num">{{ rtrim(rtrim($line->quantity, '0'), '.') }} {{ $line->unit }}</td>
                <td width="10%" class="num">{{ Money::format($line->rate) }}</td>
                <td width="9%" class="num">{{ Money::format($line->discount, true) }}</td>
                <td width="10%" class="num">{{ Money::format($line->amount) }}</td>
                <td width="7%" class="num">{{ rtrim(rtrim($line->vat_rate, '0'), '.') }}%</td>
                <td width="10%" class="num">{{ Money::format($lineVat) }}</td>
                <td width="13%" class="num">{{ Money::format(Money::toBaisa($line->amount) + $lineVat) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
<br><br>
<table cellpadding="0" cellspacing="0" nobr="true">
    <tr>
        <td width="42%">
            <span class="muted">Amount in words</span><br>
            <b>{{ $books ? $code.' '.Money::format($net + $vat).' — OMR '.Money::format($books['total']).' ('.Money::inWords($books['total']).')' : Money::inWords($net + $vat) }}</b>
            @if ($voucher->narration)<br><br><span class="muted">{{ $voucher->narration }}</span>@endif
        </td>
        <td width="2%"></td>
        <td width="56%">
            <table cellpadding="3" cellspacing="0">
                <tr><td width="35%">Total excluding VAT</td><td width="41%" dir="rtl" class="ar muted">الإجمالي غير شامل الضريبة</td><td width="24%" class="num">{{ Money::format($net) }}</td></tr>
                <tr><td width="35%">VAT</td><td width="41%" dir="rtl" class="ar muted">ضريبة القيمة المضافة</td><td width="24%" class="num">{{ Money::format($vat) }}</td></tr>
                <tr class="grand"><td width="35%">Total ({{ $code }})</td><td width="41%" dir="rtl" class="ar">الإجمالي</td><td width="24%" class="num">{{ Money::format($net + $vat) }}</td></tr>
                @if ($books)
                    <tr><td width="35%">Taxable (OMR)</td><td width="41%" dir="rtl" class="ar muted">المبلغ الخاضع</td><td width="24%" class="num">{{ Money::format($books['net']) }}</td></tr>
                    <tr><td width="35%">VAT (OMR)</td><td width="41%" dir="rtl" class="ar muted">الضريبة</td><td width="24%" class="num">{{ Money::format($books['vat']) }}</td></tr>
                    <tr><td width="35%"><b>Total (OMR)</b></td><td width="41%" dir="rtl" class="ar"><b>الإجمالي (ر.ع.)</b></td><td width="24%" class="num"><b>{{ Money::format($books['total']) }}</b></td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>
