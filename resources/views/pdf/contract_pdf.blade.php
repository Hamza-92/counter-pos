@php $pdfLocale = app()->getLocale(); $isRtl = $pdfLocale === 'ar'; @endphp
<!DOCTYPE html>
<html lang="{{ $pdfLocale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Contract - {{ $contract['contract_number'] }}</title>
    <style>
        @page { size: A4; margin: 10mm 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, body * { font-family: 'DejaVu Sans', sans-serif !important; }
        body { font-size: 9pt; color: #1f2937; line-height: 1.4; padding: 15px 20px; max-width: 100%; }
        body.rtl { direction: rtl; text-align: right; }
    </style>
</head>
<body class="{{ $isRtl ? 'rtl' : '' }}">
    <table style="width: 100%; margin-bottom: 12px;" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                @if(!empty($setting['logo']))
                    @php
                        $logoPath = public_path('images/'.$setting['logo']);
                        $logoSrc = null;
                        if (file_exists($logoPath) && is_readable($logoPath)) {
                            $logoData = @file_get_contents($logoPath);
                            if ($logoData !== false) {
                                $logoB64 = base64_encode($logoData);
                                $logoExt = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                                $logoMime = $logoExt === 'svg' ? 'image/svg+xml' : (in_array($logoExt, ['png','jpeg','jpg','gif','webp'], true) ? 'image/'.$logoExt : 'image/png');
                                if ($logoExt === 'jpg') { $logoMime = 'image/jpeg'; }
                                $logoSrc = 'data:'.$logoMime.';base64,'.$logoB64;
                            }
                        }
                    @endphp
                    @if($logoSrc)
                        <img src="{{ $logoSrc }}" alt="Logo" style="max-height: 60px; max-width: 180px;">
                    @endif
                @endif
            </td>
            <td style="width: 50%; vertical-align: top; text-align: right;">
                <div style="font-size: 18pt; font-weight: bold; color: #1a56db;">Contract</div>
                <div style="display: inline-block; background: #f3f4f6; padding: 5px 12px; border-radius: 4px; font-size: 10pt; font-weight: bold; color: #4b5563; margin-top: 6px;">{{ $contract['contract_number'] }}</div>
            </td>
        </tr>
    </table>
    <div style="height: 2px; background: #1a56db; margin: 8px 0 10px 0;"></div>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; border: 1px solid #e5e7eb;" cellpadding="0" cellspacing="0">
        <tr style="background: #1a56db;">
            <th style="padding: 6px 10px; text-align: left; font-size: 8pt; font-weight: bold; color: #ffffff; width: 35%;">Field</th>
            <th style="padding: 6px 10px; text-align: left; font-size: 8pt; font-weight: bold; color: #ffffff;">Value</th>
        </tr>
        <tr style="border-bottom: 1px solid #e5e7eb;"><td style="padding: 8px 10px; font-weight: 600; color: #6b7280;">Subject</td><td style="padding: 8px 10px;">{{ $contract['subject'] }}</td></tr>
        <tr style="border-bottom: 1px solid #e5e7eb;"><td style="padding: 8px 10px; font-weight: 600; color: #6b7280;">{{ $contract['party_label'] ?? 'Customer' }}</td><td style="padding: 8px 10px;">{{ $contract['party_name'] ?? $contract['customer_name'] }}</td></tr>
        @if(!empty($contract['project_name']))
        <tr style="border-bottom: 1px solid #e5e7eb;"><td style="padding: 8px 10px; font-weight: 600; color: #6b7280;">Project</td><td style="padding: 8px 10px;">{{ $contract['project_name'] }}</td></tr>
        @endif
        <tr style="border-bottom: 1px solid #e5e7eb;"><td style="padding: 8px 10px; font-weight: 600; color: #6b7280;">Value (USD)</td><td style="padding: 8px 10px;">{{ $contract['value_formatted'] }}</td></tr>
        <tr style="border-bottom: 1px solid #e5e7eb;"><td style="padding: 8px 10px; font-weight: 600; color: #6b7280;">Type</td><td style="padding: 8px 10px;">{{ $contract['type'] ?? '-' }}</td></tr>
        <tr style="border-bottom: 1px solid #e5e7eb;"><td style="padding: 8px 10px; font-weight: 600; color: #6b7280;">Start Date</td><td style="padding: 8px 10px;">{{ $contract['start_date'] }}</td></tr>
        <tr style="border-bottom: 1px solid #e5e7eb;"><td style="padding: 8px 10px; font-weight: 600; color: #6b7280;">End Date</td><td style="padding: 8px 10px;">{{ $contract['end_date'] }}</td></tr>
        <tr style="border-bottom: 1px solid #e5e7eb;"><td style="padding: 8px 10px; font-weight: 600; color: #6b7280;">Status</td><td style="padding: 8px 10px;">{{ ucfirst($contract['status']) }}</td></tr>
    </table>

    @if(!empty($contract['description']))
    <div style="margin-bottom: 12px;">
        <div style="font-size: 9pt; font-weight: bold; color: #1f2937; margin-bottom: 4px;">Description</div>
        <div style="padding: 8px 10px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px;">{!! $contract['description'] !!}</div>
    </div>
    @endif

    @if($contract['signer_name'] || $contract['signed_at'] || $contract['signed_ip'])
    <div style="margin-top: 12px; padding: 10px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px;">
        <div style="font-size: 9pt; font-weight: bold; color: #166534; margin-bottom: 6px;">Signature</div>
        <div style="font-size: 8pt; color: #15803d;">
            @if($contract['signer_name'])<div>Signed by: {{ $contract['signer_name'] }}</div>@endif
            @if($contract['signed_at'])<div>Date: {{ $contract['signed_at'] }}</div>@endif
            @if($contract['signed_ip'])<div>IP: {{ $contract['signed_ip'] }}</div>@endif
        </div>
    </div>
    @endif

    <div style="margin-top: 15px; padding-top: 10px; border-top: 2px solid #e5e7eb; text-align: center;">
        <p style="font-size: 9pt; color: #6b7280;">{{ $setting['CompanyName'] ?? '' }} — Contract {{ $contract['contract_number'] }}</p>
    </div>
</body>
</html>
