@php use App\Support\Money; @endphp
<x-mail.layout :company="$company">
    <p>Dear {{ $customer->name }},</p>
    @if ($statement)
        <p>Please find below the open {{ $bills->count() === 1 ? 'bill' : 'bills' }} on your account with us.</p>
    @else
        <p>According to our records, the following {{ $bills->count() === 1 ? 'bill is' : 'bills are' }} past due. If you have already paid, please ignore this reminder and accept our thanks.</p>
    @endif
    <table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse; font-size:13px; margin:12px 0;">
        <tr style="background:#e4eef1; color:#1d5b6b; text-align:left;"><th>Reference</th><th>Date</th><th>Due</th><th style="text-align:right;">Days overdue</th><th style="text-align:right;">Amount (OMR)</th></tr>
        @foreach ($bills as $bill)
            <tr style="border-bottom:1px solid #e5e7eb;">
                <td>{{ $bill->reference }}</td>
                <td>{{ $bill->bill_date?->format('d M Y') }}</td>
                <td>{{ ($bill->due_date ?? $bill->bill_date)?->format('d M Y') }}</td>
                <td style="text-align:right;">{{ $bill->overdue_days > 0 ? $bill->overdue_days : '' }}</td>
                <td style="text-align:right; font-family:monospace;">{{ Money::format($bill->pending) }}</td>
            </tr>
        @endforeach
        <tr><td colspan="4"><strong>{{ $statement ? 'Total due' : 'Total overdue' }}</strong></td><td style="text-align:right; font-family:monospace;"><strong>{{ Money::format($total) }}</strong></td></tr>
    </table>
    <p style="margin:22px 0;"><a href="{{ $link }}" style="background:#1d5b6b; color:#ffffff; padding:10px 18px; border-radius:4px; text-decoration:none; font-weight:bold;">View your statement</a></p>
    <p dir="rtl" style="text-align:right; border-top:1px solid #e5e7eb; padding-top:12px;">
        السادة {{ $customer->name_ar ?: $customer->name }} المحترمين،<br>
        @if ($statement)
            نرفق لكم كشف حسابكم، وإجمالي المبلغ المستحق <strong>{{ Money::format($total) }} ر.ع.</strong>
        @else
            نود تذكيركم بوجود مبالغ مستحقة بقيمة <strong>{{ Money::format($total) }} ر.ع.</strong> تجاوزت تاريخ الاستحقاق. إذا تم السداد، يرجى تجاهل هذه الرسالة مع الشكر.
        @endif
    </p>
</x-mail.layout>
