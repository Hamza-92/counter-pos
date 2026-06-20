@php $pdfLocale = app()->getLocale(); $isRtl = $pdfLocale === 'ar'; @endphp
<!DOCTYPE html>
<html lang="{{ $pdfLocale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Repair Quote - #{{$job['id']}}</title>
    <style>
        @page { size: A4; margin: 10mm 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, body * { font-family: 'DejaVu Sans', sans-serif !important; }
        body { font-size: 9pt; color: #1f2937; line-height: 1.4; padding: 15px 20px; }
        body.rtl { direction: rtl; text-align: right; }
        body.rtl table { direction: rtl; }
    </style>
</head>
<body class="{{ $isRtl ? 'rtl' : '' }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
    @php $cur = $symbol ?? ''; @endphp

    <!-- Header -->
    <table style="width: 100%; margin-bottom: 12px;" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 30%; vertical-align: top;">
                @php
                    $logoSrc = null;
                    if (!empty($setting['logo'])) {
                        $logoPath = public_path('images/'.$setting['logo']);
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
                    }
                @endphp
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="Logo" style="max-height: 60px; max-width: 180px;">
                @endif
            </td>
            <td style="width: 70%; vertical-align: top; text-align: right;">
                <div style="font-size: 22pt; font-weight: bold; color: #d97706; margin-bottom: 6px; letter-spacing: 1px;">REPAIR QUOTE</div>
                <div style="display: inline-block; background: #fef3c7; padding: 5px 12px; border-radius: 4px; font-size: 10pt; font-weight: bold; color: #92400e; margin-bottom: 8px;">{{$job['Ref'] ?? '#'.$job['id']}}</div>
                @if(!empty($job['quote_valid_until']))
                <div style="font-size: 8pt; color: #6b7280; margin-top: 6px;">
                    <strong>Valid until:</strong> {{ \Carbon\Carbon::parse($job['quote_valid_until'])->format('Y-m-d') }}
                </div>
                @endif
            </td>
        </tr>
    </table>

    <div style="height: 2px; background: #d97706; margin: 8px 0 14px 0;"></div>

    <!-- Customer & Company -->
    <table style="width: 100%; margin-bottom: 12px;" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 48%; vertical-align: top;">
                <div style="border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden;">
                    <div style="background: #d97706; padding: 5px 10px;">
                        <div style="color: #ffffff; font-size: 9pt; font-weight: bold; text-transform: uppercase;">QUOTE FOR</div>
                    </div>
                    <div style="padding: 8px 10px; background: #fffbeb;">
                        <div style="font-size: 10pt; font-weight: bold; margin-bottom: 4px;">{{$job['client_name']}}</div>
                        <div style="font-size: 7.5pt; color: #6b7280; line-height: 1.5;">
                            @if(!empty($job['client_phone']) && $job['client_phone'] !== '-')
                                <div><strong>Phone:</strong> {{$job['client_phone']}}</div>
                            @endif
                            @if(!empty($job['client_email']) && $job['client_email'] !== '-')
                                <div><strong>Email:</strong> {{$job['client_email']}}</div>
                            @endif
                            @if(!empty($job['client_adr']) && $job['client_adr'] !== '-')
                                <div><strong>Address:</strong> {{$job['client_adr']}}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; vertical-align: top;">
                <div style="border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden;">
                    <div style="background: #d97706; padding: 5px 10px;">
                        <div style="color: #ffffff; font-size: 9pt; font-weight: bold; text-transform: uppercase;">FROM</div>
                    </div>
                    <div style="padding: 8px 10px; background: #fffbeb;">
                        <div style="font-size: 10pt; font-weight: bold; margin-bottom: 4px;">{{$setting['CompanyName']}}</div>
                        <div style="font-size: 7.5pt; color: #6b7280; line-height: 1.5;">
                            <div><strong>Phone:</strong> {{$setting['CompanyPhone']}}</div>
                            <div><strong>Email:</strong> {{$setting['email']}}</div>
                            <div><strong>Address:</strong> {{$setting['CompanyAdress']}}</div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Device -->
    @if(!empty($job['device_brand']) || !empty($job['device_model']) || !empty($job['device_serial']))
    <div style="background: #fffbeb; border-left: 4px solid #d97706; padding: 10px 14px; margin-bottom: 12px;">
        <div style="font-size: 8pt; font-weight: bold; color: #92400e; text-transform: uppercase; margin-bottom: 4px;">Device</div>
        <div style="font-size: 10pt; color: #1f2937;">
            {{ trim(($job['device_brand'] ?? '').' '.($job['device_model'] ?? '')) }}
            @if(!empty($job['device_color'])) · {{ $job['device_color'] }}@endif
            @if(!empty($job['device_serial'])) · S/N: {{ $job['device_serial'] }}@endif
            @if(!empty($job['device_imei'])) · IMEI: {{ $job['device_imei'] }}@endif
        </div>
    </div>
    @endif

    <!-- Reported issue + diagnosis -->
    @if(!empty($job['reported_issue']) || !empty($job['diagnosis']))
    <table style="width: 100%; margin-bottom: 12px;" cellpadding="0" cellspacing="0">
        @if(!empty($job['reported_issue']))
        <tr><td style="padding: 8px 12px; background: #f9fafb; border-left: 3px solid #f59e0b; margin-bottom: 6px;">
            <div style="font-size: 8pt; font-weight: bold; color: #6b7280; text-transform: uppercase; margin-bottom: 4px;">Reported Issue</div>
            <div style="font-size: 9pt; color: #1f2937; white-space: pre-line;">{{$job['reported_issue']}}</div>
        </td></tr>
        <tr><td style="height: 6px;"></td></tr>
        @endif
        @if(!empty($job['diagnosis']))
        <tr><td style="padding: 8px 12px; background: #f9fafb; border-left: 3px solid #10b981;">
            <div style="font-size: 8pt; font-weight: bold; color: #6b7280; text-transform: uppercase; margin-bottom: 4px;">Our Diagnosis</div>
            <div style="font-size: 9pt; color: #1f2937; white-space: pre-line;">{{$job['diagnosis']}}</div>
        </td></tr>
        @endif
    </table>
    @endif

    <!-- Quote Items -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; border: 1px solid #e5e7eb;" cellpadding="0" cellspacing="0">
        <thead>
            <tr style="background: #d97706;">
                <th style="padding: 8px 10px; text-align: left; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; width: 12%;">Type</th>
                <th style="padding: 8px 10px; text-align: left; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase;">Description</th>
                <th style="padding: 8px 10px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; width: 8%;">Qty</th>
                <th style="padding: 8px 10px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; width: 14%;">Unit Price</th>
                <th style="padding: 8px 10px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; width: 16%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @if(!empty($job['items']) && count($job['items']) > 0)
                @foreach($job['items'] as $idx => $row)
                <tr style="border-bottom: 1px solid #e5e7eb; background: {{$idx % 2 == 0 ? '#ffffff' : '#fffbeb'}};">
                    <td style="padding: 8px 10px; font-size: 8pt; text-transform: uppercase; color: #6b7280;">{{ $row['type'] ?? 'part' }}</td>
                    <td style="padding: 8px 10px; font-size: 9pt;">{{ $row['description'] }}</td>
                    <td style="padding: 8px 10px; font-size: 9pt; text-align: right;">{{ $row['quantity'] }}</td>
                    <td style="padding: 8px 10px; font-size: 9pt; text-align: right;">{{ $cur }}{{ number_format((float)$row['unit_price'], 2) }}</td>
                    <td style="padding: 8px 10px; font-size: 9pt; text-align: right; font-weight: bold;">{{ $cur }}{{ number_format((float)$row['total'], 2) }}</td>
                </tr>
                @endforeach
            @else
                <tr><td colspan="5" style="padding: 12px; text-align: center; font-size: 9pt; color: #6b7280;">Quote amount: {{ $cur }}{{ number_format((float)($job['quote_amount'] ?? 0), 2) }}</td></tr>
            @endif
        </tbody>
        <tfoot>
            @if(!empty($job['diagnostic_fee']) && (float)$job['diagnostic_fee'] > 0)
            <tr style="background: #f9fafb;">
                <td colspan="4" style="padding: 8px 10px; font-size: 9pt; text-align: right;">Diagnostic Fee</td>
                <td style="padding: 8px 10px; font-size: 9pt; text-align: right;">{{ $cur }}{{ number_format((float)$job['diagnostic_fee'], 2) }}</td>
            </tr>
            @endif
            <tr style="background: #d97706; color: #ffffff;">
                <td colspan="4" style="padding: 12px; font-size: 11pt; text-align: right; font-weight: bold;">QUOTE TOTAL</td>
                <td style="padding: 12px; font-size: 14pt; text-align: right; font-weight: bold;">{{ $cur }}{{ number_format((float)$job['total_amount'], 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <!-- Warranty notice -->
    @if(!empty($job['warranty_days']) && (int)$job['warranty_days'] > 0)
    <div style="background: #ecfdf5; border-left: 4px solid #10b981; padding: 8px 12px; margin-bottom: 12px;">
        <div style="font-size: 9pt; color: #065f46;">
            <strong>Warranty:</strong> {{ $job['warranty_days'] }} days from delivery on parts and labor for the work described above.
        </div>
    </div>
    @endif

    <!-- Approval block -->
    <table style="width: 100%; margin-top: 18px;" cellpadding="0" cellspacing="0">
        <tr>
            <td style="padding: 12px; border: 1px dashed #d97706; background: #fffbeb;">
                <div style="font-size: 10pt; font-weight: bold; color: #92400e; margin-bottom: 8px; text-transform: uppercase;">CUSTOMER APPROVAL</div>
                <div style="font-size: 8pt; color: #6b7280; margin-bottom: 14px; line-height: 1.5;">
                    By signing below I authorize the work and parts described above. I understand that the diagnostic fee is non-refundable if I decline the repair, but will be credited toward the total if I approve.
                </div>
                <table style="width: 100%;" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="width: 60%; padding-right: 20px;">
                            <div style="border-bottom: 1px solid #1f2937; height: 32px;"></div>
                            <div style="font-size: 8pt; color: #6b7280; margin-top: 4px;">Customer signature</div>
                        </td>
                        <td style="width: 40%;">
                            <div style="border-bottom: 1px solid #1f2937; height: 32px;"></div>
                            <div style="font-size: 8pt; color: #6b7280; margin-top: 4px;">Date</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Footer -->
    @if(!empty($setting['is_invoice_footer']) && !empty($setting['invoice_footer']))
    <div style="margin-top: 14px; padding: 8px 10px; background: #f9fafb; border-left: 3px solid #d97706;">
        <p style="font-size: 7.5pt; color: #6b7280; line-height: 1.5; margin: 0;">{{$setting['invoice_footer']}}</p>
    </div>
    @endif
</body>
</html>
