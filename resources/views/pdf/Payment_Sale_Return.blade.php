@php $pdfLocale = app()->getLocale(); $isRtl = $pdfLocale === 'ar'; @endphp
<!DOCTYPE html>
<html lang="{{ $pdfLocale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Return Payment Receipt - {{$payment['Ref']}}</title>
    @php
        // Price formatting helper function
        $priceFormat = $setting['price_format'] ?? null;
        function formatPrice($number, $decimals = 2, $priceFormat = null) {
            $number = (float) $number;
            $decimals = (int) $decimals;
            
            if (empty($priceFormat)) {
                return number_format($number, $decimals, '.', ',');
            }
            
            switch ($priceFormat) {
                case 'comma_dot':
                    return number_format($number, $decimals, '.', ',');
                case 'dot_comma':
                    return number_format($number, $decimals, ',', '.');
                case 'space_comma':
                    return number_format($number, $decimals, ',', ' ');
                default:
                    return number_format($number, $decimals, '.', ',');
            }
        }
    @endphp
    <style>
        @page { 
            size: A4;
            margin: 10mm 15mm; 
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, body * { font-family: 'DejaVu Sans', sans-serif !important; }
        body { font-size: 9pt; color: #1f2937; line-height: 1.4; padding: 15px 20px; max-width: 100%; }
        body.rtl { direction: rtl; text-align: right; }
        body.rtl table { direction: rtl; }
    </style>
</head>
<body class="{{ $isRtl ? 'rtl' : '' }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
    <!-- Header Section -->
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
                <div style="font-size: 18pt; font-weight: bold; color: #f59e0b; margin-bottom: 6px; letter-spacing: 0.5px;">{{ __('pdf.return_payment') }}</div>
                <div style="display: inline-block; background: #fef3c7; padding: 5px 12px; border-radius: 4px; font-size: 10pt; font-weight: bold; color: #92400e; margin-bottom: 8px;">{{$payment['Ref']}}</div>
                <table style="width: 100%; font-size: 8pt; margin-top: 6px;" cellpadding="3" cellspacing="0">
                    <tr>
                        <td style="text-align: right; color: #6b7280; font-weight: 600;">{{ __('pdf.date') }}{{ $isRtl ? '' : ':' }}</td>
                        <td style="text-align: right; color: #1f2937; font-weight: 500;">
                            @php
                                $dateFormat = $setting['date_format'] ?? 'YYYY-MM-DD';
                                $dateTime = \Carbon\Carbon::parse($payment['date']);
                                $phpDateFormat = str_replace(['YYYY', 'MM', 'DD'], ['Y', 'm', 'd'], $dateFormat);
                                // Check if original date string contains time
                                $hasTime = strpos($payment['date'], ' ') !== false && preg_match('/\d{1,2}:\d{2}/', $payment['date']);
                                if ($hasTime) {
                                    $formattedDate = $dateTime->format($phpDateFormat . ' H:i');
                                    // Preserve seconds if they exist
                                    if (preg_match('/:\d{2}:\d{2}/', $payment['date'])) {
                                        $formattedDate = $dateTime->format($phpDateFormat . ' H:i:s');
                                    }
                                } else {
                                    $formattedDate = $dateTime->format($phpDateFormat);
                                }
                            @endphp
                            {{$formattedDate}}
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #6b7280; font-weight: 600;">{{ __('pdf.receipt_no') }}{{ $isRtl ? '' : ':' }}</td>
                        <td style="text-align: right; color: #1f2937; font-weight: 500;">{{$payment['Ref']}}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Divider -->
    <div style="height: 2px; background: #f59e0b; margin: 8px 0 10px 0;"></div>

    <!-- Customer & Company Info Section -->
    <table style="width: 100%; margin-bottom: 15px;" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 48%; vertical-align: top;">
                <div style="border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden;">
                    <div style="background: #f59e0b; padding: 5px 10px; border-bottom: 1px solid #d97706;">
                        <div style="color: #ffffff; font-size: 9pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px;">{{ __('pdf.refunded_to') }}</div>
                    </div>
                    <div style="padding: 10px; background: #fffbeb;">
                        <div style="font-size: 10pt; font-weight: bold; color: #1f2937; margin-bottom: 5px;">{{$payment['client_name']}}</div>
                        <div style="font-size: 7.5pt; color: #6b7280; line-height: 1.6;">
                            <div><strong style="color: #1f2937;">{{ __('pdf.phone') }}{{ $isRtl ? '' : ':' }}</strong> {{$payment['client_phone']}}</div>
                            <div><strong style="color: #1f2937;">{{ __('pdf.email') }}{{ $isRtl ? '' : ':' }}</strong> {{$payment['client_email']}}</div>
                            <div><strong style="color: #1f2937;">{{ __('pdf.address') }}{{ $isRtl ? '' : ':' }}</strong> {{$payment['client_adr']}}</div>
                        </div>
                    </div>
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; vertical-align: top;">
                <div style="border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden;">
                    <div style="background: #f59e0b; padding: 5px 10px; border-bottom: 1px solid #d97706;">
                        <div style="color: #ffffff; font-size: 9pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px;">{{ __('pdf.company_info') }}</div>
                    </div>
                    <div style="padding: 10px; background: #fffbeb;">
                        <div style="font-size: 10pt; font-weight: bold; color: #1f2937; margin-bottom: 5px;">{{$setting['CompanyName']}}</div>
                        <div style="font-size: 7.5pt; color: #6b7280; line-height: 1.6;">
                            <div><strong style="color: #1f2937;">{{ __('pdf.phone') }}{{ $isRtl ? '' : ':' }}</strong> {{$setting['CompanyPhone']}}</div>
                            <div><strong style="color: #1f2937;">{{ __('pdf.email') }}{{ $isRtl ? '' : ':' }}</strong> {{$setting['email']}}</div>
                            <div><strong style="color: #1f2937;">{{ __('pdf.address') }}{{ $isRtl ? '' : ':' }}</strong> {{$setting['CompanyAdress']}}</div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Payment Details -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; border: 1px solid #e5e7eb;" cellpadding="0" cellspacing="0">
        <thead>
            <tr style="background: #f59e0b;">
                <th style="padding: 10px; text-align: left; font-size: 9pt; font-weight: bold; color: #ffffff; text-transform: uppercase; border-right: 1px solid rgba(255,255,255,0.2);">{{ __('pdf.return_reference') }}</th>
                <th style="padding: 10px; text-align: left; font-size: 9pt; font-weight: bold; color: #ffffff; text-transform: uppercase; border-right: 1px solid rgba(255,255,255,0.2);">{{ __('pdf.payment_method') }}</th>
                <th style="padding: 10px; text-align: right; font-size: 9pt; font-weight: bold; color: #ffffff; text-transform: uppercase;">{{ __('pdf.amount_refunded') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr style="background: #fffbeb;">
                <td style="padding: 12px 10px; font-size: 10pt; font-weight: 600; color: #1f2937;">{{$payment['return_Ref']}}</td>
                <td style="padding: 12px 10px; font-size: 10pt; color: #1f2937;">{{$payment['payment_method']}}</td>
                <td style="padding: 12px 10px; text-align: right; font-size: 13pt; font-weight: bold; color: #f59e0b;">{{$symbol}} {{formatPrice((float)$payment['montant'], 2, $priceFormat)}}</td>
            </tr>
        </tbody>
    </table>

    <!-- Amount Box -->
    <div style="text-align: center; margin: 25px 0;">
        <div style="display: inline-block; background: #f59e0b; padding: 15px 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="color: rgba(255,255,255,0.9); font-size: 9pt; margin-bottom: 3px;">{{ __('pdf.total_amount_refunded') }}</div>
            <div style="color: #ffffff; font-size: 22pt; font-weight: bold;">{{$symbol}} {{formatPrice((float)$payment['montant'], 2, $priceFormat)}}</div>
        </div>
    </div>

    <!-- Footer -->
    <div style="margin-top: 30px; padding-top: 10px; border-top: 2px solid #e5e7eb;">
        @if($setting['is_invoice_footer'] && $setting['invoice_footer'] !==null)
            <div style="padding: 8px 10px; background: #fffbeb; border-left: 3px solid #f59e0b; border-radius: 3px; margin-bottom: 10px;">
                <p style="font-size: 7.5pt; color: #6b7280; line-height: 1.5; margin: 0;">{{$setting['invoice_footer']}}</p>
            </div>
        @endif
        <div style="text-align: center; padding: 8px 0;">
            <p style="font-size: 10pt; font-weight: bold; color: #f59e0b; margin: 0; letter-spacing: 0.3px;">{{ __('pdf.refund_processed') }}</p>
        </div>
    </div>
</body>
</html>
