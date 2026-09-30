@php use App\Support\Money; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statement · {{ $company->name }}</title>
    <meta name="robots" content="noindex">
    <style>
        body { margin: 0; background: #f2f6f7; font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; color: #111827; font-size: 14px; }
        .sheet { max-width: 720px; margin: 16px auto; background: #fff; border: 1px solid #c9d8dd; border-radius: 6px; overflow: hidden; }
        header { background: #1d5b6b; color: #fff; padding: 14px 20px; font-weight: bold; font-size: 16px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        main { padding: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #e4eef1; color: #1d5b6b; text-align: left; padding: 7px 6px; font-size: 11px; text-transform: uppercase; }
        td { padding: 7px 6px; border-bottom: 1px solid #e5e7eb; }
        .num { text-align: right; font-family: ui-monospace, Menlo, monospace; white-space: nowrap; }
        .overdue { color: #b91c1c; }
        .muted { color: #6b7280; }
        .total td { font-weight: bold; border-top: 2px solid #111827; }
        @media (max-width: 560px) { .hide-sm { display: none; } }
    </style>
</head>
<body>
    <div class="sheet">
        <header><span>{{ $company->name }}</span>@if ($company->name_ar)<span dir="rtl">{{ $company->name_ar }}</span>@endif</header>
        <main>
            <h2 style="margin: 0 0 4px;">Statement of account <span class="muted" style="font-weight: normal;">/ كشف حساب</span></h2>
            <p class="muted" style="margin: 0 0 16px;">{{ $ledger->name }} · as on {{ $today->format('d M Y') }}</p>

            @if ($bills->isNotEmpty())
                <table>
                    <thead><tr><th>Reference</th><th class="hide-sm">Date</th><th>Due</th><th class="num">Days overdue</th><th class="num">Amount (OMR)</th></tr></thead>
                    <tbody>
                        @foreach ($bills as $bill)
                            <tr>
                                <td>{{ $bill->reference }}</td>
                                <td class="hide-sm">{{ $bill->bill_date?->format('d M Y') }}</td>
                                <td>{{ ($bill->due_date ?? $bill->bill_date)?->format('d M Y') }}</td>
                                <td class="num {{ $bill->overdue_days > 0 ? 'overdue' : '' }}">{{ $bill->overdue_days ?: '' }}</td>
                                <td class="num">{{ Money::format($bill->pending) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <table style="margin-top: 12px;">
                <tr class="total"><td>Balance due / الرصيد المستحق</td><td class="num">OMR {{ Money::format(max($balance, 0)) }}</td></tr>
            </table>
            @if ($balance < 0)<p class="muted">You have a credit balance of OMR {{ Money::format(-$balance) }} with us.</p>@endif
            <p class="muted" style="margin-top: 20px; font-size: 12px;">{{ collect([$company->phone, $company->email])->filter()->implode(' · ') }}</p>
        </main>
    </div>
</body>
</html>
