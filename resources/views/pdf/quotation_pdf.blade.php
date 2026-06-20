@php
    $pdfLocale = app()->getLocale();
    $isRtl = $pdfLocale === 'ar';
    $rtlLabelSuffix = $isRtl ? '' : ':';
@endphp
<!DOCTYPE html>
<html lang="{{ $pdfLocale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Quotation - {{$quote['Ref']}}</title>
    @php
        $priceFormat = $setting['price_format'] ?? null;
        function formatPrice($number, $decimals = 2, $priceFormat = null) {
            $number = (float) $number;
            $decimals = (int) $decimals;
            if (empty($priceFormat)) {
                return number_format($number, $decimals, '.', ',');
            }
            switch ($priceFormat) {
                case 'comma_dot': return number_format($number, $decimals, '.', ',');
                case 'dot_comma': return number_format($number, $decimals, ',', '.');
                case 'space_comma': return number_format($number, $decimals, ',', ' ');
                default: return number_format($number, $decimals, '.', ',');
            }
        }
    @endphp
    <style>
        @page { size: A4; margin: 10mm 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, body * { font-family: 'DejaVu Sans', sans-serif !important; }
        body { font-size: 9pt; color: #1f2937; line-height: 1.4; padding: 15px 20px; max-width: 100%; }
        body.rtl { direction: rtl; text-align: right; }
        body.rtl table { direction: rtl; }
    </style>
</head>
<body class="{{ $isRtl ? 'rtl' : '' }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
    <table style="width: 100%; margin-bottom: 12px;" cellpadding="0" cellspacing="0" {{ $isRtl ? 'dir="rtl"' : '' }}>
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
            <td style="width: 70%; vertical-align: top; text-align: {{ $isRtl ? 'right' : 'right' }};">
                <div style="font-size: 18pt; font-weight: bold; color: #1a56db; margin-bottom: 6px; letter-spacing: 0.5px;">{{ __('pdf.quotation') }}</div>
                <div style="display: inline-block; background: #f3f4f6; padding: 5px 12px; border-radius: 4px; font-size: 10pt; font-weight: bold; color: #4b5563; margin-bottom: 8px;">{{$quote['Ref']}}</div>
                <table style="width: 100%; font-size: 8pt; margin-top: 6px;" cellpadding="3" cellspacing="0">
                    <tr>
                        <td style="text-align: right; color: #6b7280; font-weight: 600;">{{ __('pdf.date') }}{{ $isRtl ? '' : ':' }}</td>
                        <td style="text-align: right; color: #1f2937; font-weight: 500;">
                            @php
                                $dateFormat = $setting['date_format'] ?? 'YYYY-MM-DD';
                                $dateTime = \Carbon\Carbon::parse($quote['date']);
                                $phpDateFormat = str_replace(['YYYY', 'MM', 'DD'], ['Y', 'm', 'd'], $dateFormat);
                                $hasTime = strpos($quote['date'], ' ') !== false && preg_match('/\d{1,2}:\d{2}/', $quote['date']);
                                if ($hasTime) {
                                    $formattedDate = $dateTime->format($phpDateFormat . ' H:i');
                                    if (preg_match('/:\d{2}:\d{2}/', $quote['date'])) { $formattedDate = $dateTime->format($phpDateFormat . ' H:i:s'); }
                                } else { $formattedDate = $dateTime->format($phpDateFormat); }
                            @endphp
                            {{$formattedDate}}
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #6b7280; font-weight: 600;">{{ __('pdf.quote_no') }}{{ $isRtl ? '' : ':' }}</td>
                        <td style="text-align: right; color: #1f2937; font-weight: 500;">{{$quote['Ref']}}</td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #6b7280; font-weight: 600;">{{ __('pdf.status') }}{{ $isRtl ? '' : ':' }}</td>
                        <td style="text-align: right;">
                            @php
                                $statusColors = ['sent' => ['bg' => '#d1fae5', 'color' => '#065f46'], 'pending' => ['bg' => '#fef3c7', 'color' => '#92400e'], 'draft' => ['bg' => '#e5e7eb', 'color' => '#374151']];
                                $statusKey = strtolower($quote['statut']);
                                $statusStyle = $statusColors[$statusKey] ?? ['bg' => '#dbeafe', 'color' => '#1e40af'];
                            @endphp
                            <span style="background: {{$statusStyle['bg']}}; color: {{$statusStyle['color']}}; padding: 3px 8px; border-radius: 3px; font-size: 7pt; font-weight: bold; text-transform: uppercase;">{{$quote['statut']}}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div style="height: 2px; background: #1a56db; margin: 8px 0 10px 0;"></div>

    <!-- Quote For / From: same as sale_pdf — RTL = value left, label right -->
    <table style="width: 100%; margin-bottom: 12px;" cellpadding="0" cellspacing="0" {{ $isRtl ? 'dir="rtl"' : '' }}>
        <tr>
            <td style="width: 48%; vertical-align: top;">
                <div style="border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden;">
                    <div style="background: #1a56db; padding: 5px 10px; border-bottom: 1px solid #3b82f6; text-align: {{ $isRtl ? 'right' : 'left' }};">
                        <div style="color: #ffffff; font-size: 9pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px;">{{ __('pdf.quote_for') }}</div>
                    </div>
                    <div style="padding: 8px 10px; background: #f9fafb;">
                        <div style="font-size: 10pt; font-weight: bold; color: #1f2937; margin-bottom: 4px; text-align: {{ $isRtl ? 'right' : 'left' }};">{{$quote['client_name']}}</div>
                        <table style="width: 100%; font-size: 7.5pt; color: #6b7280; line-height: 1.5;" cellpadding="0" cellspacing="0">
                            @if($isRtl)
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left; direction: ltr;">{{$quote['client_phone']}}</td><td style="width: 32%; padding: 1px 0; vertical-align: top; text-align: right; direction: rtl;"><strong style="color: #1f2937;">{{ __('pdf.phone') }}</strong></td></tr>
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left; direction: ltr;">{{$quote['client_email']}}</td><td style="padding: 1px 0; vertical-align: top; text-align: right; direction: rtl;"><strong style="color: #1f2937;">{{ __('pdf.email') }}</strong></td></tr>
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left; direction: ltr;">{{$quote['client_adr']}}</td><td style="padding: 1px 0; vertical-align: top; text-align: right; direction: rtl;"><strong style="color: #1f2937;">{{ __('pdf.address') }}</strong></td></tr>
                            @if($quote['client_tax'])
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left; direction: ltr;">{{$quote['client_tax']}}</td><td style="padding: 1px 0; vertical-align: top; text-align: right; direction: rtl;"><strong style="color: #1f2937;">{{ __('pdf.tax_no') }}</strong></td></tr>
                            @endif
                            @else
                            <tr><td style="width: 28%; padding: 1px 0; vertical-align: top; text-align: left;"><strong style="color: #1f2937;">{{ __('pdf.phone') }}:</strong></td><td style="padding: 1px 0; vertical-align: top; text-align: left;">{{$quote['client_phone']}}</td></tr>
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left;"><strong style="color: #1f2937;">{{ __('pdf.email') }}:</strong></td><td style="padding: 1px 0; vertical-align: top; text-align: left;">{{$quote['client_email']}}</td></tr>
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left;"><strong style="color: #1f2937;">{{ __('pdf.address') }}:</strong></td><td style="padding: 1px 0; vertical-align: top; text-align: left;">{{$quote['client_adr']}}</td></tr>
                            @if($quote['client_tax'])
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left;"><strong style="color: #1f2937;">{{ __('pdf.tax_no') }}:</strong></td><td style="padding: 1px 0; vertical-align: top; text-align: left;">{{$quote['client_tax']}}</td></tr>
                            @endif
                            @endif
                        </table>
                    </div>
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; vertical-align: top;">
                <div style="border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden;">
                    <div style="background: #1a56db; padding: 5px 10px; border-bottom: 1px solid #3b82f6; text-align: {{ $isRtl ? 'right' : 'left' }};">
                        <div style="color: #ffffff; font-size: 9pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px;">{{ __('pdf.from') }}</div>
                    </div>
                    <div style="padding: 8px 10px; background: #f9fafb;">
                        <div style="font-size: 10pt; font-weight: bold; color: #1f2937; margin-bottom: 4px; text-align: {{ $isRtl ? 'right' : 'left' }};">{{$setting['CompanyName']}}</div>
                        <table style="width: 100%; font-size: 7.5pt; color: #6b7280; line-height: 1.5;" cellpadding="0" cellspacing="0">
                            @if($isRtl)
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left; direction: ltr;">{{$setting['CompanyPhone']}}</td><td style="width: 32%; padding: 1px 0; vertical-align: top; text-align: right; direction: rtl;"><strong style="color: #1f2937;">{{ __('pdf.phone') }}</strong></td></tr>
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left; direction: ltr;">{{$setting['email']}}</td><td style="padding: 1px 0; vertical-align: top; text-align: right; direction: rtl;"><strong style="color: #1f2937;">{{ __('pdf.email') }}</strong></td></tr>
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left; direction: ltr;">{{$setting['CompanyAdress']}}</td><td style="padding: 1px 0; vertical-align: top; text-align: right; direction: rtl;"><strong style="color: #1f2937;">{{ __('pdf.address') }}</strong></td></tr>
                            @else
                            <tr><td style="width: 28%; padding: 1px 0; vertical-align: top; text-align: left;"><strong style="color: #1f2937;">{{ __('pdf.phone') }}:</strong></td><td style="padding: 1px 0; vertical-align: top; text-align: left;">{{$setting['CompanyPhone']}}</td></tr>
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left;"><strong style="color: #1f2937;">{{ __('pdf.email') }}:</strong></td><td style="padding: 1px 0; vertical-align: top; text-align: left;">{{$setting['email']}}</td></tr>
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left;"><strong style="color: #1f2937;">{{ __('pdf.address') }}:</strong></td><td style="padding: 1px 0; vertical-align: top; text-align: left;">{{$setting['CompanyAdress']}}</td></tr>
                            @endif
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px; border: 1px solid #e5e7eb;" cellpadding="0" cellspacing="0" {{ $isRtl ? 'dir="rtl"' : '' }}>
        <thead>
            <tr style="background: #1a56db;">
                <th style="padding: 6px 5px; text-align: {{ $isRtl ? 'right' : 'left' }}; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; border-right: 1px solid rgba(255,255,255,0.2);">{{ __('pdf.product') }}</th>
                <th style="padding: 6px 5px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; border-right: 1px solid rgba(255,255,255,0.2);">{{ __('pdf.price') }}</th>
                <th style="padding: 6px 5px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; border-right: 1px solid rgba(255,255,255,0.2);">{{ __('pdf.qty') }}</th>
                <th style="padding: 6px 5px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; border-right: 1px solid rgba(255,255,255,0.2);">{{ __('pdf.disc') }}</th>
                <th style="padding: 6px 5px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; border-right: 1px solid rgba(255,255,255,0.2);">{{ __('pdf.tax') }}</th>
                <th style="padding: 6px 5px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase;">{{ __('pdf.total') }}</th>
            </tr>
        </thead>
        <tbody>
            @php $rowIndex = 0; @endphp
            @foreach ($details as $detail)
            <tr style="border-bottom: 1px solid #e5e7eb; background: {{$rowIndex % 2 == 0 ? '#ffffff' : '#f9fafb'}};">
                <td style="padding: 5px; vertical-align: top;">
                    <div style="font-weight: 600; font-size: 8.5pt; color: #1f2937; margin-bottom: 1px;">{{$detail['name']}}</div>
                    <div style="font-size: 7pt; color: #6b7280;">{{ __('pdf.code') }} {{$detail['code']}}</div>
                    @if($detail['is_imei'] && $detail['imei_number'] !==null)
                        <div style="font-size: 7pt; color: #3b82f6; margin-top: 1px;">{{ __('pdf.sn') }} {{$detail['imei_number']}}</div>
                    @endif
                </td>
                <td style="padding: 5px; text-align: right; font-size: 8.5pt; color: #1f2937;">{{formatPrice((float)$detail['price'], 2, $priceFormat)}}</td>
                <td style="padding: 5px; text-align: right; font-size: 8.5pt; color: #1f2937;">{{$detail['quantity']}} {{$detail['unitSale']}}</td>
                <td style="padding: 5px; text-align: right; font-size: 8.5pt; color: #ef4444;">{{formatPrice((float)$detail['DiscountNet'], 2, $priceFormat)}}</td>
                <td style="padding: 5px; text-align: right; font-size: 8.5pt; color: #1f2937;">{{formatPrice((float)$detail['taxe'], 2, $priceFormat)}}</td>
                <td style="padding: 5px; text-align: right; font-size: 9pt; font-weight: bold; color: #1a56db;">{{formatPrice((float)$detail['total'], 2, $priceFormat)}}</td>
            </tr>
            @php $rowIndex++; @endphp
            @endforeach
        </tbody>
    </table>

    @php
        $tdAmountLeft = 'padding: 5px 10px; font-size: 8.5pt; font-weight: 600; text-align: left; direction: ltr;';
        $tdLabelRight = 'padding: 5px 10px; font-size: 8pt; font-weight: 600; text-align: right; direction: rtl;';
    @endphp
    <table style="width: 100%; margin-bottom: 10px;" cellpadding="0" cellspacing="0" {{ $isRtl ? 'dir="rtl"' : '' }}>
        <tr>
            <td style="width: 58%;"></td>
            <td style="width: 42%; vertical-align: top; text-align: {{ $isRtl ? 'right' : 'left' }};">
                <table style="width: 100%; border: 1px solid #e5e7eb; border-radius: 4px; border-collapse: collapse;" cellpadding="0" cellspacing="0">
                    @if($isRtl)
                    <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                        <td style="{{ $tdAmountLeft }} color: #1f2937;">{{$symbol}} {{formatPrice((float)($quote['GrandTotal'] - $quote['TaxNet'] + $quote['discount'] - $quote['shipping']), 2, $priceFormat)}}</td>
                        <td style="{{ $tdLabelRight }} color: #6b7280;">{{ __('pdf.subtotal') }}{!! $rtlLabelSuffix !!}</td>
                    </tr>
                    <tr style="background: #ffffff; border-bottom: 1px solid #e5e7eb;">
                        <td style="{{ $tdAmountLeft }} color: #1f2937;">{{$symbol}} {{formatPrice((float)$quote['TaxNet'], 2, $priceFormat)}}</td>
                        <td style="{{ $tdLabelRight }} color: #6b7280;">{{ __('pdf.order_tax') }}{!! $rtlLabelSuffix !!}</td>
                    </tr>
                    <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                        <td style="{{ $tdAmountLeft }} color: #ef4444;">- {{$symbol}} {{formatPrice((float)$quote['discount'], 2, $priceFormat)}}</td>
                        <td style="{{ $tdLabelRight }} color: #6b7280;">{{ __('pdf.discount') }}{!! $rtlLabelSuffix !!}</td>
                    </tr>
                    <tr style="background: #ffffff; border-bottom: 1px solid #e5e7eb;">
                        <td style="{{ $tdAmountLeft }} color: #1f2937;">{{$symbol}} {{formatPrice((float)$quote['shipping'], 2, $priceFormat)}}</td>
                        <td style="{{ $tdLabelRight }} color: #6b7280;">{{ __('pdf.shipping') }}{!! $rtlLabelSuffix !!}</td>
                    </tr>
                    <tr style="background: #1a56db;">
                        <td style="padding: 8px 10px; font-size: 11pt; font-weight: bold; color: #ffffff; text-align: left; direction: ltr;">{{$symbol}} {{formatPrice((float)$quote['GrandTotal'], 2, $priceFormat)}}</td>
                        <td style="padding: 8px 10px; font-size: 10pt; font-weight: bold; color: #ffffff; text-align: right; direction: rtl;">{{ __('pdf.total_label') }}{!! $rtlLabelSuffix !!}</td>
                    </tr>
                    @else
                    <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 5px 10px; font-size: 8pt; font-weight: 600; color: #6b7280;">{{ __('pdf.subtotal') }}</td>
                        <td style="padding: 5px 10px; text-align: right; font-size: 8.5pt; font-weight: 600; color: #1f2937;">{{$symbol}} {{formatPrice((float)($quote['GrandTotal'] - $quote['TaxNet'] + $quote['discount'] - $quote['shipping']), 2, $priceFormat)}}</td>
                    </tr>
                    <tr style="background: #ffffff; border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 5px 10px; font-size: 8pt; font-weight: 600; color: #6b7280;">{{ __('pdf.order_tax') }}</td>
                        <td style="padding: 5px 10px; text-align: right; font-size: 8.5pt; font-weight: 600; color: #1f2937;">{{$symbol}} {{formatPrice((float)$quote['TaxNet'], 2, $priceFormat)}}</td>
                    </tr>
                    <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 5px 10px; font-size: 8pt; font-weight: 600; color: #6b7280;">{{ __('pdf.discount') }}</td>
                        <td style="padding: 5px 10px; text-align: right; font-size: 8.5pt; font-weight: 600; color: #ef4444;">- {{$symbol}} {{formatPrice((float)$quote['discount'], 2, $priceFormat)}}</td>
                    </tr>
                    <tr style="background: #ffffff; border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 5px 10px; font-size: 8pt; font-weight: 600; color: #6b7280;">{{ __('pdf.shipping') }}</td>
                        <td style="padding: 5px 10px; text-align: right; font-size: 8.5pt; font-weight: 600; color: #1f2937;">{{$symbol}} {{formatPrice((float)$quote['shipping'], 2, $priceFormat)}}</td>
                    </tr>
                    <tr style="background: #1a56db;">
                        <td style="padding: 8px 10px; font-size: 10pt; font-weight: bold; color: #ffffff;">{{ __('pdf.total_label') }}</td>
                        <td style="padding: 8px 10px; text-align: right; font-size: 11pt; font-weight: bold; color: #ffffff;">{{$symbol}} {{formatPrice((float)$quote['GrandTotal'], 2, $priceFormat)}}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div style="margin-top: 15px; padding-top: 10px; border-top: 2px solid #e5e7eb; text-align: {{ $isRtl ? 'right' : 'left' }};">
        @if($setting['is_invoice_footer'] && $setting['invoice_footer'] !==null)
            <div style="padding: 8px 10px; background: #f9fafb; border-{{ $isRtl ? 'right' : 'left' }}: 3px solid #1a56db; border-radius: 3px; margin-bottom: 10px;">
                <p style="font-size: 7.5pt; color: #6b7280; line-height: 1.5; margin: 0;">{{$setting['invoice_footer']}}</p>
            </div>
        @endif
        <p style="font-size: 10pt; font-weight: bold; color: #1a56db; margin: 0; letter-spacing: 0.3px;">{{ __('pdf.thank_you') }}</p>
    </div>
</body>
</html>
