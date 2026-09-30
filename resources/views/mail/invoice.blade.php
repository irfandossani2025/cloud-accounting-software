@php use App\Support\Money; @endphp
<x-mail.layout :company="$company">
    <p>Dear {{ $voucher->party?->name ?? 'Customer' }},</p>
    <p>Please find attached our {{ strtolower($title) }} <strong>{{ $voucher->number }}</strong> dated {{ $voucher->date->format('d M Y') }}
       for <strong>{{ $code }} {{ Money::format($net + $vat) }}</strong>@if ($voucher->due_date), due on <strong>{{ $voucher->due_date->format('d M Y') }}</strong>@endif.</p>
    @if ($note)<p style="white-space:pre-line;">{{ $note }}</p>@endif
    <p style="margin:22px 0;"><a href="{{ $link }}" style="background:#1d5b6b; color:#ffffff; padding:10px 18px; border-radius:4px; text-decoration:none; font-weight:bold;">View {{ strtolower($title) }} online</a></p>
    <p dir="rtl" style="text-align:right; border-top:1px solid #e5e7eb; padding-top:12px;">
        السادة {{ $voucher->party?->name_ar ?: $voucher->party?->name }} المحترمين،<br>
        مرفق {{ $titleAr }} رقم <strong>{{ $voucher->number }}</strong> بتاريخ {{ $voucher->date->format('d/m/Y') }} بقيمة <strong>{{ Money::format($net + $vat) }} {{ $code === 'OMR' ? 'ر.ع.' : $code }}</strong>.
    </p>
    <p style="color:#6b7280; font-size:12px;">The link is valid for {{ \App\Services\CommunicationService::LINK_DAYS }} days.</p>
</x-mail.layout>
