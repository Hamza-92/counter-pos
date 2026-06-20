@php $pdfLocale = app()->getLocale(); $isRtl = $pdfLocale === 'ar'; @endphp
<!DOCTYPE html>
<html lang="{{ $pdfLocale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Supplier Report - {{$provider['provider_name']}}</title>
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
                <div style="font-size: 18pt; font-weight: bold; color: #8b5cf6; margin-bottom: 6px; letter-spacing: 0.5px;">{{ __('pdf.supplier_report') }}</div>
                <div style="display: inline-block; background: #ede9fe; padding: 5px 12px; border-radius: 4px; font-size: 10pt; font-weight: bold; color: #6b21a8; margin-bottom: 8px;">{{$provider['provider_name']}}</div>
            </td>
        </tr>
    </table>

    <!-- Divider -->
    <div style="height: 2px; background: #8b5cf6; margin: 8px 0 10px 0;"></div>

    <!-- Supplier & Company Info Section -->
    <table style="width: 100%; margin-bottom: 15px;" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 48%; vertical-align: top;">
                <div style="border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden;">
                    <div style="background: #8b5cf6; padding: 5px 10px; border-bottom: 1px solid #7c3aed;">
                        <div style="color: #ffffff; font-size: 9pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px;">{{ __('pdf.supplier_details') }}</div>
                    </div>
                    <div style="padding: 10px; background: #faf5ff;">
                        <div style="font-size: 10pt; font-weight: bold; color: #1f2937; margin-bottom: 8px;">{{$provider['provider_name']}}</div>
                        <div style="font-size: 7.5pt; color: #6b7280; line-height: 1.7;">
                            <div><strong style="color: #1f2937;">{{ __('pdf.phone') }}{{ $isRtl ? '' : ':' }}</strong> {{$provider['phone']}}</div>
                            <div><strong style="color: #1f2937;">{{ __('pdf.total_purchases') }}{{ $isRtl ? '' : ':' }}</strong> {{$provider['total_purchase']}}</div>
                            <div style="margin-top: 5px; padding-top: 5px; border-top: 1px solid #ddd6fe;">
                                <div><strong style="color: #1f2937;">{{ __('pdf.total_amount') }}{{ $isRtl ? '' : ':' }}</strong> {{$symbol}} {{formatPrice((float)$provider['total_amount'], 2, $priceFormat)}}</div>
                                <div><strong style="color: #1f2937;">{{ __('pdf.total_paid') }}{{ $isRtl ? '' : ':' }}</strong> {{$symbol}} {{formatPrice((float)$provider['total_paid'], 2, $priceFormat)}}</div>
                                <div><strong style="color: #ef4444;">{{ __('pdf.purchase_due') }}{{ $isRtl ? '' : ':' }}</strong> {{$symbol}} {{formatPrice((float)$provider['due'], 2, $priceFormat)}}</div>
                                <div><strong style="color: #f59e0b;">{{ __('pdf.return_due') }}{{ $isRtl ? '' : ':' }}</strong> {{$symbol}} {{formatPrice((float)$provider['return_Due'], 2, $priceFormat)}}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; vertical-align: top;">
                <div style="border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden;">
                    <div style="background: #8b5cf6; padding: 5px 10px; border-bottom: 1px solid #7c3aed;">
                        <div style="color: #ffffff; font-size: 9pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px;">{{ __('pdf.company_info') }}</div>
                    </div>
                    <div style="padding: 10px; background: #faf5ff;">
                        <div style="font-size: 10pt; font-weight: bold; color: #1f2937; margin-bottom: 5px;">{{$setting['CompanyName']}}</div>
                        <div style="font-size: 7.5pt; color: #6b7280; line-height: 1.7;">
                            <div><strong style="color: #1f2937;">{{ __('pdf.phone') }}{{ $isRtl ? '' : ':' }}</strong> {{$setting['CompanyPhone']}}</div>
                            <div><strong style="color: #1f2937;">{{ __('pdf.email') }}{{ $isRtl ? '' : ':' }}</strong> {{$setting['email']}}</div>
                            <div><strong style="color: #1f2937;">{{ __('pdf.address') }}{{ $isRtl ? '' : ':' }}</strong> {{$setting['CompanyAdress']}}</div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Purchases List -->
    <div style="margin-bottom: 10px; padding: 8px; background: #faf5ff; border-left: 3px solid #8b5cf6;">
        <h3 style="font-size: 10pt; color: #8b5cf6; margin: 0;">{{ __('pdf.all_purchases') }}</h3>
    </div>

    <table style="width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb;" cellpadding="0" cellspacing="0">
        <thead>
            <tr style="background: #8b5cf6;">
                <th style="padding: 8px 8px; text-align: left; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; border-right: 1px solid rgba(255,255,255,0.2);">{{ __('pdf.date') }}</th>
                <th style="padding: 8px 8px; text-align: left; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; border-right: 1px solid rgba(255,255,255,0.2);">{{ __('pdf.ref') }}</th>
                <th style="padding: 8px 8px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; border-right: 1px solid rgba(255,255,255,0.2);">{{ __('pdf.paid') }}</th>
                <th style="padding: 8px 8px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; border-right: 1px solid rgba(255,255,255,0.2);">{{ __('pdf.due') }}</th>
                <th style="padding: 8px 8px; text-align: left; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase;">{{ __('pdf.payment_status') }}</th>
            </tr>
        </thead>
        <tbody>
            @php $rowIndex = 0; @endphp
            @foreach ($purchases as $purchase)
            <tr style="border-bottom: 1px solid #e5e7eb; background: {{$rowIndex % 2 == 0 ? '#ffffff' : '#faf5ff'}};">
                <td style="padding: 8px; font-size: 8.5pt; color: #1f2937;">
                    @php
                        $dateFormat = $setting['date_format'] ?? 'YYYY-MM-DD';
                        $dateTime = \Carbon\Carbon::parse($purchase['date']);
                        $phpDateFormat = str_replace(['YYYY', 'MM', 'DD'], ['Y', 'm', 'd'], $dateFormat);
                        // Check if original date string contains time
                        $hasTime = strpos($purchase['date'], ' ') !== false && preg_match('/\d{1,2}:\d{2}/', $purchase['date']);
                        if ($hasTime) {
                            $formattedDate = $dateTime->format($phpDateFormat . ' H:i');
                            // Preserve seconds if they exist
                            if (preg_match('/:\d{2}:\d{2}/', $purchase['date'])) {
                                $formattedDate = $dateTime->format($phpDateFormat . ' H:i:s');
                            }
                        } else {
                            $formattedDate = $dateTime->format($phpDateFormat);
                        }
                    @endphp
                    {{$formattedDate}}
                </td>
                <td style="padding: 8px; font-size: 8.5pt; font-weight: 600; color: #8b5cf6;">{{$purchase['Ref']}}</td>
                <td style="padding: 8px; text-align: right; font-size: 8.5pt; color: #10b981;">{{$symbol}} {{formatPrice((float)$purchase['paid_amount'], 2, $priceFormat)}}</td>
                <td style="padding: 8px; text-align: right; font-size: 8.5pt; font-weight: bold; color: #ef4444;">{{$symbol}} {{formatPrice((float)$purchase['due'], 2, $priceFormat)}}</td>
                <td style="padding: 8px; font-size: 8pt;">
                    @php
                        $paymentColors = [
                            'paid' => ['bg' => '#d1fae5', 'color' => '#065f46'],
                            'partial' => ['bg' => '#fef3c7', 'color' => '#92400e'],
                            'unpaid' => ['bg' => '#fee2e2', 'color' => '#991b1b'],
                        ];
                        $paymentKey = strtolower($purchase['payment_status']);
                        $paymentStyle = $paymentColors[$paymentKey] ?? ['bg' => '#e5e7eb', 'color' => '#374151'];
                    @endphp
                    <span style="background: {{$paymentStyle['bg']}}; color: {{$paymentStyle['color']}}; padding: 3px 6px; border-radius: 3px; font-size: 7pt; font-weight: bold; text-transform: uppercase;">{{$purchase['payment_status']}}</span>
                </td>
            </tr>
            @php $rowIndex++; @endphp
            @endforeach
        </tbody>
    </table>

    <!-- Footer -->
    <div style="margin-top: 20px; padding-top: 10px; border-top: 2px solid #e5e7eb; text-align: center;">
        <p style="font-size: 9pt; color: #8b5cf6; font-weight: bold; margin: 0;">{{ __('pdf.supplier_report') }}</p>
    </div>
</body>
</html>
