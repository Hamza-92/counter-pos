@php $pdfLocale = app()->getLocale(); $isRtl = $pdfLocale === 'ar'; @endphp
<!DOCTYPE html>
<html lang="{{ $pdfLocale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Booking - #{{$booking['id']}}</title>
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
                <div style="font-size: 18pt; font-weight: bold; color: #1a56db; margin-bottom: 6px; letter-spacing: 0.5px;">{{ __('pdf.booking') }}</div>
                <div style="display: inline-block; background: #f3f4f6; padding: 5px 12px; border-radius: 4px; font-size: 10pt; font-weight: bold; color: #4b5563; margin-bottom: 8px;">{{$booking['Ref'] ?? '#'.$booking['id']}}</div>
                <table style="width: 100%; font-size: 8pt; margin-top: 6px;" cellpadding="3" cellspacing="0">
                    <tr>
                        <td style="text-align: right; color: #6b7280; font-weight: 600;">Date:</td>
                        <td style="text-align: right; color: #1f2937; font-weight: 500;">
                            @php
                                $dateFormat = $setting['date_format'] ?? 'YYYY-MM-DD';
                                $dateTime = \Carbon\Carbon::parse($booking['booking_date']);
                                $phpDateFormat = str_replace(['YYYY', 'MM', 'DD'], ['Y', 'm', 'd'], $dateFormat);
                                $formattedDate = $dateTime->format($phpDateFormat);
                            @endphp
                            {{$formattedDate}}
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #6b7280; font-weight: 600;">{{ __('pdf.reference') }}{{ $isRtl ? '' : ':' }}</td>
                        <td style="text-align: right; color: #1f2937; font-weight: 500;">{{$booking['Ref'] ?? '#'.$booking['id']}}</td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #6b7280; font-weight: 600;">{{ __('pdf.status') }}{{ $isRtl ? '' : ':' }}</td>
                        <td style="text-align: right;">
                            @php
                                $statusColors = [
                                    'pending' => ['bg' => '#fef3c7', 'color' => '#92400e'],
                                    'confirmed' => ['bg' => '#d1fae5', 'color' => '#065f46'],
                                    'cancelled' => ['bg' => '#fee2e2', 'color' => '#991b1b'],
                                    'completed' => ['bg' => '#dbeafe', 'color' => '#1e40af'],
                                ];
                                $statusKey = strtolower($booking['status']);
                                $statusStyle = $statusColors[$statusKey] ?? ['bg' => '#e5e7eb', 'color' => '#374151'];
                            @endphp
                            <span style="background: {{$statusStyle['bg']}}; color: {{$statusStyle['color']}}; padding: 3px 8px; border-radius: 3px; font-size: 7pt; font-weight: bold; text-transform: uppercase;">{{$booking['status']}}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Divider -->
    <div style="height: 2px; background: #1a56db; margin: 8px 0 10px 0;"></div>

    <!-- Customer & Company Info Section -->
    <table style="width: 100%; margin-bottom: 12px;" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 48%; vertical-align: top;">
                <div style="border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden;">
                    <div style="background: #1a56db; padding: 5px 10px; border-bottom: 1px solid #3b82f6;">
                        <div style="color: #ffffff; font-size: 9pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px;">{{ __('pdf.customer') }}</div>
                    </div>
                    <div style="padding: 8px 10px; background: #f9fafb;">
                        <div style="font-size: 10pt; font-weight: bold; color: #1f2937; margin-bottom: 4px;">{{$booking['customer_name']}}</div>
                        <div style="font-size: 7.5pt; color: #6b7280; line-height: 1.5;">
                            @if($booking['customer_phone'] && $booking['customer_phone'] !== '-')
                                <div><strong style="color: #1f2937;">{{ __('pdf.phone') }}{{ $isRtl ? '' : ':' }}</strong> {{$booking['customer_phone']}}</div>
                            @endif
                            @if($booking['customer_email'] && $booking['customer_email'] !== '-')
                                <div><strong style="color: #1f2937;">{{ __('pdf.email') }}{{ $isRtl ? '' : ':' }}</strong> {{$booking['customer_email']}}</div>
                            @endif
                            @if($booking['customer_adr'] && $booking['customer_adr'] !== '-')
                                <div><strong style="color: #1f2937;">{{ __('pdf.address') }}{{ $isRtl ? '' : ':' }}</strong> {{$booking['customer_adr']}}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; vertical-align: top;">
                <div style="border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden;">
                    <div style="background: #1a56db; padding: 5px 10px; border-bottom: 1px solid #3b82f6;">
                        <div style="color: #ffffff; font-size: 9pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px;">{{ __('pdf.company_info') }}</div>
                    </div>
                    <div style="padding: 8px 10px; background: #f9fafb;">
                        <div style="font-size: 10pt; font-weight: bold; color: #1f2937; margin-bottom: 4px;">{{$setting['CompanyName']}}</div>
                        <div style="font-size: 7.5pt; color: #6b7280; line-height: 1.5;">
                            <div><strong style="color: #1f2937;">{{ __('pdf.phone') }}{{ $isRtl ? '' : ':' }}</strong> {{$setting['CompanyPhone']}}</div>
                            <div><strong style="color: #1f2937;">{{ __('pdf.email') }}{{ $isRtl ? '' : ':' }}</strong> {{$setting['email']}}</div>
                            <div><strong style="color: #1f2937;">{{ __('pdf.address') }}{{ $isRtl ? '' : ':' }}</strong> {{$setting['CompanyAdress']}}</div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Booking Details Table -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px; border: 1px solid #e5e7eb;" cellpadding="0" cellspacing="0">
        <thead>
            <tr style="background: #1a56db;">
                <th style="padding: 6px 10px; text-align: left; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; border-right: 1px solid rgba(255,255,255,0.2);">{{ __('pdf.service_details') }}</th>
                <th style="padding: 6px 10px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase;">{{ __('pdf.value') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-bottom: 1px solid #e5e7eb; background: #ffffff;">
                <td style="padding: 8px 10px; font-weight: 600; font-size: 8.5pt; color: #1f2937;">{{ __('pdf.service') }}</td>
                <td style="padding: 8px 10px; text-align: right; font-size: 8.5pt; color: #1f2937;">{{$booking['product_name']}}</td>
            </tr>
            <tr style="border-bottom: 1px solid #e5e7eb; background: #f9fafb;">
                <td style="padding: 8px 10px; font-weight: 600; font-size: 8.5pt; color: #1f2937;">{{ __('pdf.price') }}</td>
                <td style="padding: 8px 10px; text-align: right; font-size: 9pt; font-weight: bold; color: #1a56db;">
                    @if($booking['price'] && $booking['price'] > 0)
                        {{$symbol}} {{formatPrice((float)$booking['price'], 2, $priceFormat)}}
                    @else
                        -
                    @endif
                </td>
            </tr>
            <tr style="border-bottom: 1px solid #e5e7eb; background: #ffffff;">
                <td style="padding: 8px 10px; font-weight: 600; font-size: 8.5pt; color: #1f2937;">Booking Date</td>
                <td style="padding: 8px 10px; text-align: right; font-size: 8.5pt; color: #1f2937;">
                    @php
                        $dateFormat = $setting['date_format'] ?? 'YYYY-MM-DD';
                        $dateTime = \Carbon\Carbon::parse($booking['booking_date']);
                        $phpDateFormat = str_replace(['YYYY', 'MM', 'DD'], ['Y', 'm', 'd'], $dateFormat);
                        $formattedDate = $dateTime->format($phpDateFormat);
                    @endphp
                    {{$formattedDate}}
                </td>
            </tr>
            <tr style="border-bottom: 1px solid #e5e7eb; background: #f9fafb;">
                <td style="padding: 8px 10px; font-weight: 600; font-size: 8.5pt; color: #1f2937;">{{ __('pdf.start_time') }}</td>
                <td style="padding: 8px 10px; text-align: right; font-size: 8.5pt; color: #1f2937;">{{$booking['booking_time']}}</td>
            </tr>
            @if($booking['booking_end_time'])
            <tr style="border-bottom: 1px solid #e5e7eb; background: #ffffff;">
                <td style="padding: 8px 10px; font-weight: 600; font-size: 8.5pt; color: #1f2937;">{{ __('pdf.end_time') }}</td>
                <td style="padding: 8px 10px; text-align: right; font-size: 8.5pt; color: #1f2937;">{{$booking['booking_end_time']}}</td>
            </tr>
            @endif
            <tr style="border-bottom: 1px solid #e5e7eb; background: {{$booking['booking_end_time'] ? '#f9fafb' : '#ffffff'}};">
                <td style="padding: 8px 10px; font-weight: 600; font-size: 8.5pt; color: #1f2937;">{{ __('pdf.status') }}</td>
                <td style="padding: 8px 10px; text-align: right; font-size: 8.5pt; color: #1f2937;">
                    @php
                        $statusColors = [
                            'pending' => ['bg' => '#fef3c7', 'color' => '#92400e'],
                            'confirmed' => ['bg' => '#d1fae5', 'color' => '#065f46'],
                            'cancelled' => ['bg' => '#fee2e2', 'color' => '#991b1b'],
                            'completed' => ['bg' => '#dbeafe', 'color' => '#1e40af'],
                        ];
                        $statusKey = strtolower($booking['status']);
                        $statusStyle = $statusColors[$statusKey] ?? ['bg' => '#e5e7eb', 'color' => '#374151'];
                    @endphp
                    <span style="background: {{$statusStyle['bg']}}; color: {{$statusStyle['color']}}; padding: 3px 8px; border-radius: 3px; font-size: 7pt; font-weight: bold; text-transform: uppercase;">{{$booking['status']}}</span>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Notes Section -->
    @if($booking['notes'])
    <div style="margin-top: 15px; padding: 10px; background: #f9fafb; border-left: 3px solid #1a56db; border-radius: 3px;">
        <div style="font-size: 8pt; font-weight: bold; color: #1f2937; margin-bottom: 5px; text-transform: uppercase;">{{ __('pdf.notes') }}</div>
        <div style="font-size: 8pt; color: #6b7280; line-height: 1.5; white-space: pre-line;">{{$booking['notes']}}</div>
    </div>
    @endif

    <!-- Footer -->
    <div style="margin-top: 15px; padding-top: 10px; border-top: 2px solid #e5e7eb;">
        @if($setting['is_invoice_footer'] && $setting['invoice_footer'] !==null)
            <div style="padding: 8px 10px; background: #f9fafb; border-left: 3px solid #1a56db; border-radius: 3px; margin-bottom: 10px;">
                <p style="font-size: 7.5pt; color: #6b7280; line-height: 1.5; margin: 0;">{{$setting['invoice_footer']}}</p>
            </div>
        @endif
        <div style="text-align: center; padding: 8px 0;">
            <p style="font-size: 10pt; font-weight: bold; color: #1a56db; margin: 0; letter-spacing: 0.3px;">{{ __('pdf.thank_you_booking') }}</p>
        </div>
    </div>
</body>
</html>

