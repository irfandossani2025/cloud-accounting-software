@props(['title', 'titleAr', 'voucher', 'company', 'pdfUrl' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} {{ $voucher->number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; color: #111827; font-size: 12px; margin: 0; background: #f1f5f9; }
        .sheet { max-width: 210mm; margin: 16px auto; background: #fff; padding: 14mm; box-shadow: 0 1px 4px rgba(0,0,0,.12); }
        .ar { font-family: 'Noto Naskh Arabic', 'Geeza Pro', 'Arial', serif; direction: rtl; }
        .row { display: flex; justify-content: space-between; gap: 16px; }
        .muted { color: #6b7280; }
        h1 { margin: 0; font-size: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { background: #f3f4f6; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; text-align: left; }
        th .ar { display: block; text-transform: none; font-size: 11px; letter-spacing: 0; font-weight: 600; }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; }
        .totals td { border: none; padding: 3px 6px; }
        .grand td { border-top: 2px solid #111827; font-weight: 700; font-size: 14px; }
        .toolbar { max-width: 210mm; margin: 16px auto 0; text-align: right; }
        .toolbar button { padding: 6px 14px; border-radius: 6px; border: 0; background: #1f6f68; color: #fff; cursor: pointer; }
        @media print { body { background: #fff; } .sheet { margin: 0; padding: 0; box-shadow: none; max-width: none; } .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        @if ($pdfUrl)<a href="{{ $pdfUrl }}" style="display:inline-block; margin-right:8px; padding:6px 14px; border-radius:6px; border:1px solid #1f6f68; color:#1f6f68; text-decoration:none;">Download PDF</a>@endif
        <button onclick="window.print()">Print</button>
    </div>
    <div class="sheet">
        <div class="row" style="align-items: flex-start; border-bottom: 2px solid #1f6f68; padding-bottom: 10px; margin-bottom: 12px;">
            <div>
                <h1>{{ $company->name }}</h1>
                <div class="muted" style="white-space: pre-line">{{ $company->address }}</div>
                @if ($company->vatin)<div>VATIN: <strong>{{ $company->vatin }}</strong></div>@endif
                @if ($company->cr_number)<div>CR No: {{ $company->cr_number }}</div>@endif
                @if ($company->phone || $company->email)<div class="muted">{{ collect([$company->phone, $company->email])->filter()->implode(' · ') }}</div>@endif
            </div>
            <div class="ar" style="text-align: right">
                @if ($company->name_ar)<h1>{{ $company->name_ar }}</h1>@endif
                @if ($company->address_ar)<div class="muted" style="white-space: pre-line">{{ $company->address_ar }}</div>@endif
                @if ($company->vatin)<div>الرقم الضريبي: <strong>{{ $company->vatin }}</strong></div>@endif
                @if ($company->cr_number)<div>السجل التجاري: {{ $company->cr_number }}</div>@endif
            </div>
        </div>

        <div class="row" style="align-items: baseline; margin-bottom: 12px;">
            <h1 style="font-size: 18px">{{ $title }}</h1>
            <h1 class="ar" style="font-size: 20px">{{ $titleAr }}</h1>
        </div>

        {{ $slot }}
    </div>
</body>
</html>
