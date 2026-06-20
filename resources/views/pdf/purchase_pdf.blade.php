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
    <title>Purchase Invoice - {{$purchase['Ref']}}</title>
    @php
        $priceFormat = $setting['price_format'] ?? null;
        function formatPrice($number, $decimals = 2, $priceFormat = null) {
            $number = (float) $number;
            $decimals = (int) $decimals;
            if (empty($priceFormat)) { return number_format($number, $decimals, '.', ','); }
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
                <div style="font-size: 18pt; font-weight: bold; color: #1a56db; margin-bottom: 6px; letter-spacing: 0.5px;">{{ __('pdf.purchase_order') }}</div>
                <div style="display: inline-block; background: #f3f4f6; padding: 5px 12px; border-radius: 4px; font-size: 10pt; font-weight: bold; color: #4b5563; margin-bottom: 8px;">{{$purchase['Ref']}}</div>
                <table style="width: 100%; font-size: 8pt; margin-top: 6px;" cellpadding="3" cellspacing="0">
                    <tr>
                        <td style="text-align: right; color: #6b7280; font-weight: 600;">{{ __('pdf.date') }}{{ $isRtl ? '' : ':' }}</td>
                        <td style="text-align: right; color: #1f2937; font-weight: 500;">
                            @php
                                $dateFormat = $setting['date_format'] ?? 'YYYY-MM-DD';
                                $dateTime = \Carbon\Carbon::parse($purchase['date']);
                                $phpDateFormat = str_replace(['YYYY', 'MM', 'DD'], ['Y', 'm', 'd'], $dateFormat);
                                $hasTime = strpos($purchase['date'], ' ') !== false && preg_match('/\d{1,2}:\d{2}/', $purchase['date']);
                                if ($hasTime) {
                                    $formattedDate = $dateTime->format($phpDateFormat . ' H:i');
                                    if (preg_match('/:\d{2}:\d{2}/', $purchase['date'])) { $formattedDate = $dateTime->format($phpDateFormat . ' H:i:s'); }
                                } else { $formattedDate = $dateTime->format($phpDateFormat); }
                            @endphp
                            {{$formattedDate}}
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #6b7280; font-weight: 600;">{{ __('pdf.order_no') }}{{ $isRtl ? '' : ':' }}</td>
                        <td style="text-align: right; color: #1f2937; font-weight: 500;">{{$purchase['Ref']}}</td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #6b7280; font-weight: 600;">{{ __('pdf.status') }}{{ $isRtl ? '' : ':' }}</td>
                        <td style="text-align: right;">
                            @php
                                $statusColors = ['completed' => ['bg' => '#d1fae5', 'color' => '#065f46'], 'received' => ['bg' => '#d1fae5', 'color' => '#065f46'], 'pending' => ['bg' => '#fef3c7', 'color' => '#92400e'], 'ordered' => ['bg' => '#dbeafe', 'color' => '#1e40af']];
                                $statusKey = strtolower($purchase['statut']);
                                $statusStyle = $statusColors[$statusKey] ?? ['bg' => '#e5e7eb', 'color' => '#374151'];
                            @endphp
                            <span style="background: {{$statusStyle['bg']}}; color: {{$statusStyle['color']}}; padding: 3px 8px; border-radius: 3px; font-size: 7pt; font-weight: bold; text-transform: uppercase;">{{$purchase['statut']}}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #6b7280; font-weight: 600;">{{ __('pdf.payment') }}{{ $isRtl ? '' : ':' }}</td>
                        <td style="text-align: right;">
                            @php $paymentKey = strtolower($purchase['payment_status']); $paymentStyle = $statusColors[$paymentKey] ?? ['bg' => '#e5e7eb', 'color' => '#374151']; @endphp
                            <span style="background: {{$paymentStyle['bg']}}; color: {{$paymentStyle['color']}}; padding: 3px 8px; border-radius: 3px; font-size: 7pt; font-weight: bold; text-transform: uppercase;">{{$purchase['payment_status']}}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div style="height: 2px; background: #1a56db; margin: 8px 0 10px 0;"></div>

    <!-- Supplier / Company: RTL = value left, label right -->
    <table style="width: 100%; margin-bottom: 12px;" cellpadding="0" cellspacing="0" {{ $isRtl ? 'dir="rtl"' : '' }}>
        <tr>
            <td style="width: 48%; vertical-align: top;">
                <div style="border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden;">
                    <div style="background: #1a56db; padding: 5px 10px; border-bottom: 1px solid #3b82f6; text-align: {{ $isRtl ? 'right' : 'left' }};">
                        <div style="color: #ffffff; font-size: 9pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px;">{{ __('pdf.supplier_info') }}</div>
                    </div>
                    <div style="padding: 8px 10px; background: #f9fafb;">
                        <div style="font-size: 10pt; font-weight: bold; color: #1f2937; margin-bottom: 4px; text-align: {{ $isRtl ? 'right' : 'left' }};">{{$purchase['supplier_name']}}</div>
                        <table style="width: 100%; font-size: 7.5pt; color: #6b7280; line-height: 1.5;" cellpadding="0" cellspacing="0">
                            @if($isRtl)
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left; direction: ltr;">{{$purchase['supplier_phone']}}</td><td style="width: 32%; padding: 1px 0; vertical-align: top; text-align: right; direction: rtl;"><strong style="color: #1f2937;">{{ __('pdf.phone') }}</strong></td></tr>
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left; direction: ltr;">{{$purchase['supplier_email']}}</td><td style="padding: 1px 0; vertical-align: top; text-align: right; direction: rtl;"><strong style="color: #1f2937;">{{ __('pdf.email') }}</strong></td></tr>
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left; direction: ltr;">{{$purchase['supplier_adr']}}</td><td style="padding: 1px 0; vertical-align: top; text-align: right; direction: rtl;"><strong style="color: #1f2937;">{{ __('pdf.address') }}</strong></td></tr>
                            @if($purchase['supplier_tax'])
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left; direction: ltr;">{{$purchase['supplier_tax']}}</td><td style="padding: 1px 0; vertical-align: top; text-align: right; direction: rtl;"><strong style="color: #1f2937;">{{ __('pdf.tax_no') }}</strong></td></tr>
                            @endif
                            @else
                            <tr><td style="width: 28%; padding: 1px 0; vertical-align: top; text-align: left;"><strong style="color: #1f2937;">{{ __('pdf.phone') }}:</strong></td><td style="padding: 1px 0; vertical-align: top; text-align: left;">{{$purchase['supplier_phone']}}</td></tr>
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left;"><strong style="color: #1f2937;">{{ __('pdf.email') }}:</strong></td><td style="padding: 1px 0; vertical-align: top; text-align: left;">{{$purchase['supplier_email']}}</td></tr>
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left;"><strong style="color: #1f2937;">{{ __('pdf.address') }}:</strong></td><td style="padding: 1px 0; vertical-align: top; text-align: left;">{{$purchase['supplier_adr']}}</td></tr>
                            @if($purchase['supplier_tax'])
                            <tr><td style="padding: 1px 0; vertical-align: top; text-align: left;"><strong style="color: #1f2937;">{{ __('pdf.tax_no') }}:</strong></td><td style="padding: 1px 0; vertical-align: top; text-align: left;">{{$purchase['supplier_tax']}}</td></tr>
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
                        <div style="color: #ffffff; font-size: 9pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px;">{{ __('pdf.company_info') }}</div>
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
                <th style="padding: 6px 5px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; border-right: 1px solid rgba(255,255,255,0.2);">{{ __('pdf.cost') }}</th>
                <th style="padding: 6px 5px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; border-right: 1px solid rgba(255,255,255,0.2);">{{ __('pdf.qty') }}</th>
                <th style="padding: 6px 5px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; border-right: 1px solid rgba(255,255,255,0.2);">{{ __('pdf.disc') }}</th>
                <th style="padding: 6px 5px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; border-right: 1px solid rgba(255,255,255,0.2);">{{ __('pdf.tax') }}</th>
                <th style="padding: 6px 5px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase;">{{ __('pdf.total') }}</th>
            </tr>
        </thead>
        <tbody>
            @php
                $rowIndex = 0;
                $pdfDateFormat = $setting['date_format'] ?? 'YYYY-MM-DD';
                $phpBatchDateFormat = str_replace(['YYYY', 'MM', 'DD'], ['Y', 'm', 'd'], $pdfDateFormat);
                $formatBatchDate = function ($d) use ($phpBatchDateFormat) {
                    if (empty($d)) return null;
                    try { return \Carbon\Carbon::parse($d)->format($phpBatchDateFormat); }
                    catch (\Exception $e) { return (string) $d; }
                };
            @endphp
            @foreach ($details as $detail)
            <tr style="border-bottom: 1px solid #e5e7eb; background: {{$rowIndex % 2 == 0 ? '#ffffff' : '#f9fafb'}};">
                <td style="padding: 5px; vertical-align: top;">
                    <div style="font-weight: 600; font-size: 8.5pt; color: #1f2937; margin-bottom: 1px;">{{$detail['name']}}</div>
                    <div style="font-size: 7pt; color: #6b7280;">{{ __('pdf.code') }} {{$detail['code']}}</div>
                    @if($detail['is_imei'] && $detail['imei_number'] !==null)
                        <div style="font-size: 7pt; color: #3b82f6; margin-top: 1px;">{{ __('pdf.sn') }} {{$detail['imei_number']}}</div>
                    @endif
                </td>
                <td style="padding: 5px; text-align: right; font-size: 8.5pt; color: #1f2937;">{{$detail['cost']}}</td>
                <td style="padding: 5px; text-align: right; font-size: 8.5pt; color: #1f2937;">{{$detail['quantity']}} {{$detail['unit_purchase']}}</td>
                <td style="padding: 5px; text-align: right; font-size: 8.5pt; color: #ef4444;">{{$detail['DiscountNet']}}</td>
                <td style="padding: 5px; text-align: right; font-size: 8.5pt; color: #1f2937;">{{$detail['taxe']}}</td>
                <td style="padding: 5px; text-align: right; font-size: 9pt; font-weight: bold; color: #1a56db;">{{$detail['total']}}</td>
            </tr>
            @if(!empty($detail['is_batch_tracked']) && !empty($detail['batches']))
            <tr style="background: #ffffff;">
                <td colspan="6" style="padding: 0; background: #ffffff;">
                    <div style="margin: 4px 6px 8px 6px; border: 1px solid #e0e7ff; border-radius: 5px; overflow: hidden; background: #f8faff;">
                        <table style="width: 100%; border-collapse: collapse;" cellpadding="0" cellspacing="0">
                            <tr style="background: #4f46e5;">
                                <td style="padding: 5px 10px; text-align: {{ $isRtl ? 'right' : 'left' }}; color: #ffffff; font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px;">{{ __('pdf.batches') }}</td>
                                <td style="padding: 5px 10px; text-align: {{ $isRtl ? 'left' : 'right' }};">
                                    <span style="display: inline-block; background: #6d6ff2; color: #ffffff; font-size: 7pt; font-weight: bold; padding: 1px 8px; border-radius: 8px;">{{ count($detail['batches']) }}</span>
                                </td>
                            </tr>
                        </table>
                        <table style="width: 100%; border-collapse: collapse; font-size: 7.5pt;" cellpadding="0" cellspacing="0">
                            <thead>
                                <tr style="background: #eef2ff;">
                                    <th style="padding: 5px 8px; text-align: {{ $isRtl ? 'right' : 'left' }}; color: #3730a3; font-weight: bold; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.3px; border-bottom: 1px solid #c7d2fe;">{{ __('pdf.batch_no') }}</th>
                                    <th style="padding: 5px 8px; text-align: {{ $isRtl ? 'right' : 'left' }}; color: #3730a3; font-weight: bold; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.3px; border-bottom: 1px solid #c7d2fe;">{{ __('pdf.mfg') }}</th>
                                    <th style="padding: 5px 8px; text-align: {{ $isRtl ? 'right' : 'left' }}; color: #3730a3; font-weight: bold; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.3px; border-bottom: 1px solid #c7d2fe;">{{ __('pdf.exp') }}</th>
                                    <th style="padding: 5px 8px; text-align: right; color: #3730a3; font-weight: bold; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.3px; border-bottom: 1px solid #c7d2fe;">{{ __('pdf.qty') }}</th>
                                    <th style="padding: 5px 8px; text-align: right; color: #3730a3; font-weight: bold; text-transform: uppercase; font-size: 7pt; letter-spacing: 0.3px; border-bottom: 1px solid #c7d2fe;">{{ __('pdf.cost') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($detail['batches'] as $bIdx => $b)
                                    @php
                                        $expiryStyle = 'background: #f3f4f6; color: #6b7280;';
                                        if (!empty($b['expiry_date'])) {
                                            try {
                                                $expDate = \Carbon\Carbon::parse($b['expiry_date'])->startOfDay();
                                                $today = \Carbon\Carbon::now()->startOfDay();
                                                $diffDays = $today->diffInDays($expDate, false);
                                                if ($diffDays < 0) { $expiryStyle = 'background: #fee2e2; color: #991b1b;'; }
                                                elseif ($diffDays <= 30) { $expiryStyle = 'background: #fef3c7; color: #92400e;'; }
                                                else { $expiryStyle = 'background: #d1fae5; color: #065f46;'; }
                                            } catch (\Exception $e) {}
                                        }
                                        $qtyStr = number_format((float)($b['qty'] ?? 0), 2, '.', '');
                                        $unitCost = isset($b['unit_cost']) && $b['unit_cost'] !== null
                                            ? $symbol.' '.formatPrice((float)$b['unit_cost'], 2, $priceFormat)
                                            : '—';
                                        $rowBg = $bIdx % 2 === 1 ? '#f8faff' : '#ffffff';
                                        $mfgFormatted = $formatBatchDate($b['mfg_date'] ?? null);
                                        $expFormatted = $formatBatchDate($b['expiry_date'] ?? null);
                                    @endphp
                                    <tr style="background: {{ $rowBg }}; border-top: 1px solid #e0e7ff;">
                                        <td style="padding: 5px 8px; text-align: {{ $isRtl ? 'right' : 'left' }}; font-weight: bold; color: #1f2937;">
                                            @if(!empty($b['batch_no']))
                                                {{ $b['batch_no'] }}
                                            @else
                                                <span style="color: #9ca3af; font-style: italic;">—</span>
                                            @endif
                                        </td>
                                        <td style="padding: 5px 8px; text-align: {{ $isRtl ? 'right' : 'left' }}; color: #374151;">
                                            @if($mfgFormatted)
                                                {{ $mfgFormatted }}
                                            @else
                                                <span style="color: #9ca3af;">—</span>
                                            @endif
                                        </td>
                                        <td style="padding: 5px 8px; text-align: {{ $isRtl ? 'right' : 'left' }};">
                                            @if($expFormatted)
                                                <span style="display: inline-block; padding: 2px 8px; border-radius: 8px; font-size: 7pt; font-weight: bold; {{ $expiryStyle }}">{{ $expFormatted }}</span>
                                            @else
                                                <span style="color: #9ca3af;">—</span>
                                            @endif
                                        </td>
                                        <td style="padding: 5px 8px; text-align: right; color: #1f2937; font-weight: bold;">{{ $qtyStr }} {{ $detail['unit_purchase'] }}</td>
                                        <td style="padding: 5px 8px; text-align: right; color: #4f46e5; font-weight: bold;">{{ $unitCost }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
            @endif
            @php $rowIndex++; @endphp
            @endforeach
        </tbody>
    </table>

    @php $tdAmountLeft = 'padding: 5px 10px; font-size: 8.5pt; font-weight: 600; text-align: left; direction: ltr;'; $tdLabelRight = 'padding: 5px 10px; font-size: 8pt; font-weight: 600; text-align: right; direction: rtl;'; @endphp
    <table style="width: 100%; margin-bottom: 10px;" cellpadding="0" cellspacing="0" {{ $isRtl ? 'dir="rtl"' : '' }}>
        <tr>
            <td style="width: 58%;"></td>
            <td style="width: 42%; vertical-align: top; text-align: {{ $isRtl ? 'right' : 'left' }};">
                <table style="width: 100%; border: 1px solid #e5e7eb; border-radius: 4px; border-collapse: collapse;" cellpadding="0" cellspacing="0">
                    @if($isRtl)
                    <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                        <td style="{{ $tdAmountLeft }} color: #1f2937;">{{$symbol}} {{formatPrice((float)($purchase['GrandTotal'] - $purchase['TaxNet'] + $purchase['discount'] - $purchase['shipping']), 2, $priceFormat)}}</td>
                        <td style="{{ $tdLabelRight }} color: #6b7280;">{{ __('pdf.subtotal') }}{!! $rtlLabelSuffix !!}</td>
                    </tr>
                    <tr style="background: #ffffff; border-bottom: 1px solid #e5e7eb;">
                        <td style="{{ $tdAmountLeft }} color: #1f2937;">{{$symbol}} {{formatPrice((float)$purchase['TaxNet'], 2, $priceFormat)}}</td>
                        <td style="{{ $tdLabelRight }} color: #6b7280;">{{ __('pdf.order_tax') }}{!! $rtlLabelSuffix !!}</td>
                    </tr>
                    <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                        <td style="{{ $tdAmountLeft }} color: #ef4444;">- {{$symbol}} {{formatPrice((float)$purchase['discount'], 2, $priceFormat)}}</td>
                        <td style="{{ $tdLabelRight }} color: #6b7280;">{{ __('pdf.discount') }}{!! $rtlLabelSuffix !!}</td>
                    </tr>
                    <tr style="background: #ffffff; border-bottom: 1px solid #e5e7eb;">
                        <td style="{{ $tdAmountLeft }} color: #1f2937;">{{$symbol}} {{formatPrice((float)$purchase['shipping'], 2, $priceFormat)}}</td>
                        <td style="{{ $tdLabelRight }} color: #6b7280;">{{ __('pdf.shipping') }}{!! $rtlLabelSuffix !!}</td>
                    </tr>
                    <tr style="background: #1a56db;">
                        <td style="padding: 8px 10px; font-size: 11pt; font-weight: bold; color: #ffffff; text-align: left; direction: ltr;">{{$symbol}} {{formatPrice((float)$purchase['GrandTotal'], 2, $priceFormat)}}</td>
                        <td style="padding: 8px 10px; font-size: 10pt; font-weight: bold; color: #ffffff; text-align: right; direction: rtl;">{{ __('pdf.total_label') }}{!! $rtlLabelSuffix !!}</td>
                    </tr>
                    <tr style="background: #d1fae5; border-bottom: 1px solid #a7f3d0;">
                        <td style="padding: 6px 10px; font-size: 9pt; font-weight: bold; color: #065f46; text-align: left; direction: ltr;">{{$symbol}} {{formatPrice((float)$purchase['paid_amount'], 2, $priceFormat)}}</td>
                        <td style="padding: 6px 10px; font-size: 8.5pt; font-weight: bold; color: #065f46; text-align: right; direction: rtl;">{{ __('pdf.paid_amount') }}{!! $rtlLabelSuffix !!}</td>
                    </tr>
                    <tr style="background: #fef3c7;">
                        <td style="padding: 6px 10px; font-size: 9pt; font-weight: bold; color: #92400e; text-align: left; direction: ltr;">{{$symbol}} {{formatPrice((float)$purchase['due'], 2, $priceFormat)}}</td>
                        <td style="padding: 6px 10px; font-size: 8.5pt; font-weight: bold; color: #92400e; text-align: right; direction: rtl;">{{ __('pdf.amount_due') }}{!! $rtlLabelSuffix !!}</td>
                    </tr>
                    @else
                    <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 5px 10px; font-size: 8pt; font-weight: 600; color: #6b7280;">{{ __('pdf.subtotal') }}</td>
                        <td style="padding: 5px 10px; text-align: right; font-size: 8.5pt; font-weight: 600; color: #1f2937;">{{$symbol}} {{formatPrice((float)($purchase['GrandTotal'] - $purchase['TaxNet'] + $purchase['discount'] - $purchase['shipping']), 2, $priceFormat)}}</td>
                    </tr>
                    <tr style="background: #ffffff; border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 5px 10px; font-size: 8pt; font-weight: 600; color: #6b7280;">{{ __('pdf.order_tax') }}</td>
                        <td style="padding: 5px 10px; text-align: right; font-size: 8.5pt; font-weight: 600; color: #1f2937;">{{$symbol}} {{formatPrice((float)$purchase['TaxNet'], 2, $priceFormat)}}</td>
                    </tr>
                    <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 5px 10px; font-size: 8pt; font-weight: 600; color: #6b7280;">{{ __('pdf.discount') }}</td>
                        <td style="padding: 5px 10px; text-align: right; font-size: 8.5pt; font-weight: 600; color: #ef4444;">- {{$symbol}} {{formatPrice((float)$purchase['discount'], 2, $priceFormat)}}</td>
                    </tr>
                    <tr style="background: #ffffff; border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 5px 10px; font-size: 8pt; font-weight: 600; color: #6b7280;">{{ __('pdf.shipping') }}</td>
                        <td style="padding: 5px 10px; text-align: right; font-size: 8.5pt; font-weight: 600; color: #1f2937;">{{$symbol}} {{formatPrice((float)$purchase['shipping'], 2, $priceFormat)}}</td>
                    </tr>
                    <tr style="background: #1a56db;">
                        <td style="padding: 8px 10px; font-size: 10pt; font-weight: bold; color: #ffffff;">{{ __('pdf.total_label') }}</td>
                        <td style="padding: 8px 10px; text-align: right; font-size: 11pt; font-weight: bold; color: #ffffff;">{{$symbol}} {{formatPrice((float)$purchase['GrandTotal'], 2, $priceFormat)}}</td>
                    </tr>
                    <tr style="background: #d1fae5; border-bottom: 1px solid #a7f3d0;">
                        <td style="padding: 6px 10px; font-size: 8.5pt; font-weight: bold; color: #065f46;">{{ __('pdf.paid_amount') }}</td>
                        <td style="padding: 6px 10px; text-align: right; font-size: 9pt; font-weight: bold; color: #065f46;">{{$symbol}} {{formatPrice((float)$purchase['paid_amount'], 2, $priceFormat)}}</td>
                    </tr>
                    <tr style="background: #fef3c7;">
                        <td style="padding: 6px 10px; font-size: 8.5pt; font-weight: bold; color: #92400e;">{{ __('pdf.amount_due') }}</td>
                        <td style="padding: 6px 10px; text-align: right; font-size: 9pt; font-weight: bold; color: #92400e;">{{$symbol}} {{formatPrice((float)$purchase['due'], 2, $priceFormat)}}</td>
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
