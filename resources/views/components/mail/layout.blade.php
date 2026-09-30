@props(['company'])
<!DOCTYPE html>
<html lang="en">
<body style="margin:0; background:#f2f6f7; font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif; color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f6f7; padding:24px 12px;">
        <tr><td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background:#ffffff; border:1px solid #c9d8dd; border-radius:6px;">
                <tr><td style="background:#1d5b6b; color:#ffffff; padding:14px 20px; font-size:16px; font-weight:bold; border-radius:6px 6px 0 0;">
                    {{ $company->name }}@if ($company->name_ar)<span style="float:right;" dir="rtl">{{ $company->name_ar }}</span>@endif
                </td></tr>
                <tr><td style="padding:20px; font-size:14px; line-height:1.55;">{{ $slot }}</td></tr>
                <tr><td style="padding:12px 20px; font-size:11px; color:#6b7280; border-top:1px solid #e5e7eb;">
                    {{ collect([$company->phone, $company->email, $company->vatin ? 'VATIN '.$company->vatin : null])->filter()->implode(' · ') }}
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
