@php $pdfLocale = app()->getLocale(); $isRtl = $pdfLocale === 'ar'; @endphp
<!DOCTYPE html>
<html lang="{{ $pdfLocale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Service Job - #{{$job['id']}}</title>
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
    @php
        $statusColors = [
            'pending' => ['bg' => '#fef3c7', 'color' => '#92400e'],
            'intake' => ['bg' => '#fef3c7', 'color' => '#92400e'],
            'diagnostic' => ['bg' => '#e0f2fe', 'color' => '#075985'],
            'quoted' => ['bg' => '#e0f2fe', 'color' => '#075985'],
            'approved' => ['bg' => '#dbeafe', 'color' => '#1e40af'],
            'in_progress' => ['bg' => '#dbeafe', 'color' => '#1e40af'],
            'ready' => ['bg' => '#d1fae5', 'color' => '#065f46'],
            'delivered' => ['bg' => '#d1fae5', 'color' => '#065f46'],
            'completed' => ['bg' => '#d1fae5', 'color' => '#065f46'],
            'declined' => ['bg' => '#fee2e2', 'color' => '#991b1b'],
            'cancelled' => ['bg' => '#fee2e2', 'color' => '#991b1b'],
        ];
        $statusKey = strtolower($job['status'] ?? 'pending');
        $statusStyle = $statusColors[$statusKey] ?? ['bg' => '#e5e7eb', 'color' => '#374151'];
        $statusLabel = ucfirst(str_replace('_', ' ', $job['status'] ?? ''));

        $paymentColors = [
            'paid' => ['bg' => '#d1fae5', 'color' => '#065f46'],
            'partial' => ['bg' => '#fef3c7', 'color' => '#92400e'],
            'unpaid' => ['bg' => '#fee2e2', 'color' => '#991b1b'],
        ];
        $paymentKey = strtolower($job['payment_status'] ?? 'unpaid');
        $paymentStyle = $paymentColors[$paymentKey] ?? ['bg' => '#e5e7eb', 'color' => '#374151'];

        $cur = $symbol ?? '';
    @endphp

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
                <div style="font-size: 18pt; font-weight: bold; color: #1a56db; margin-bottom: 6px; letter-spacing: 0.5px;">{{ __('pdf.service_job') }}</div>
                <div style="display: inline-block; background: #f3f4f6; padding: 5px 12px; border-radius: 4px; font-size: 10pt; font-weight: bold; color: #4b5563; margin-bottom: 8px;">{{$job['Ref'] ?? '#'.$job['id']}}</div>
                <table style="width: 100%; font-size: 8pt; margin-top: 6px;" cellpadding="3" cellspacing="0">
                    <tr>
                        <td style="text-align: right; color: #6b7280; font-weight: 600;">{{ __('pdf.date') }}{{ $isRtl ? '' : ':' }}</td>
                        <td style="text-align: right; color: #1f2937; font-weight: 500;">
                            @if(!empty($job['scheduled_date']))
                                @php
                                    $dateFormat = $setting['date_format'] ?? 'YYYY-MM-DD';
                                    $dateTime = \Carbon\Carbon::parse($job['scheduled_date']);
                                    $phpDateFormat = str_replace(['YYYY', 'MM', 'DD'], ['Y', 'm', 'd'], $dateFormat);
                                    $formattedDate = $dateTime->format($phpDateFormat);
                                @endphp
                                {{$formattedDate}}
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #6b7280; font-weight: 600;">{{ __('pdf.status') }}{{ $isRtl ? '' : ':' }}</td>
                        <td style="text-align: right;">
                            <span style="background: {{$statusStyle['bg']}}; color: {{$statusStyle['color']}}; padding: 3px 8px; border-radius: 3px; font-size: 7pt; font-weight: bold; text-transform: uppercase;">{{$statusLabel}}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #6b7280; font-weight: 600;">Payment:</td>
                        <td style="text-align: right;">
                            <span style="background: {{$paymentStyle['bg']}}; color: {{$paymentStyle['color']}}; padding: 3px 8px; border-radius: 3px; font-size: 7pt; font-weight: bold; text-transform: uppercase;">{{ ucfirst($paymentKey) }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Divider -->
    <div style="height: 2px; background: #1a56db; margin: 8px 0 10px 0;"></div>

    <!-- Customer & Company Info -->
    <table style="width: 100%; margin-bottom: 12px;" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 48%; vertical-align: top;">
                <div style="border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden;">
                    <div style="background: #1a56db; padding: 5px 10px;">
                        <div style="color: #ffffff; font-size: 9pt; font-weight: bold; text-transform: uppercase;">{{ __('pdf.customer') }}</div>
                    </div>
                    <div style="padding: 8px 10px; background: #f9fafb;">
                        <div style="font-size: 10pt; font-weight: bold; color: #1f2937; margin-bottom: 4px;">{{$job['client_name']}}</div>
                        <div style="font-size: 7.5pt; color: #6b7280; line-height: 1.5;">
                            @if(!empty($job['client_phone']) && $job['client_phone'] !== '-')
                                <div><strong style="color: #1f2937;">{{ __('pdf.phone') }}{{ $isRtl ? '' : ':' }}</strong> {{$job['client_phone']}}</div>
                            @endif
                            @if(!empty($job['client_email']) && $job['client_email'] !== '-')
                                <div><strong style="color: #1f2937;">{{ __('pdf.email') }}{{ $isRtl ? '' : ':' }}</strong> {{$job['client_email']}}</div>
                            @endif
                            @if(!empty($job['client_adr']) && $job['client_adr'] !== '-')
                                <div><strong style="color: #1f2937;">{{ __('pdf.address') }}{{ $isRtl ? '' : ':' }}</strong> {{$job['client_adr']}}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; vertical-align: top;">
                <div style="border: 1px solid #e5e7eb; border-radius: 4px; overflow: hidden;">
                    <div style="background: #1a56db; padding: 5px 10px;">
                        <div style="color: #ffffff; font-size: 9pt; font-weight: bold; text-transform: uppercase;">COMPANY</div>
                    </div>
                    <div style="padding: 8px 10px; background: #f9fafb;">
                        <div style="font-size: 10pt; font-weight: bold; color: #1f2937; margin-bottom: 4px;">{{$setting['CompanyName']}}</div>
                        <div style="font-size: 7.5pt; color: #6b7280; line-height: 1.5;">
                            <div><strong style="color: #1f2937;">Phone:</strong> {{$setting['CompanyPhone']}}</div>
                            <div><strong style="color: #1f2937;">Email:</strong> {{$setting['email']}}</div>
                            <div><strong style="color: #1f2937;">Address:</strong> {{$setting['CompanyAdress']}}</div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Device Information -->
    @if(!empty($job['device_brand']) || !empty($job['device_model']) || !empty($job['device_serial']) || !empty($job['device_imei']))
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px; border: 1px solid #e5e7eb;" cellpadding="0" cellspacing="0">
        <thead>
            <tr style="background: #1a56db;">
                <th colspan="4" style="padding: 6px 10px; text-align: left; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase;">Device Information</th>
            </tr>
        </thead>
        <tbody>
            <tr style="background: #ffffff;">
                @if(!empty($job['device_brand']))
                <td style="padding: 6px 10px; font-size: 8pt; width: 25%;"><strong style="color: #6b7280;">Brand:</strong><br>{{$job['device_brand']}}</td>
                @endif
                @if(!empty($job['device_model']))
                <td style="padding: 6px 10px; font-size: 8pt; width: 25%;"><strong style="color: #6b7280;">Model:</strong><br>{{$job['device_model']}}</td>
                @endif
                @if(!empty($job['device_color']))
                <td style="padding: 6px 10px; font-size: 8pt; width: 25%;"><strong style="color: #6b7280;">Color:</strong><br>{{$job['device_color']}}</td>
                @endif
                @if(!empty($job['device_serial']))
                <td style="padding: 6px 10px; font-size: 8pt; width: 25%;"><strong style="color: #6b7280;">Serial:</strong><br>{{$job['device_serial']}}</td>
                @endif
            </tr>
            @if(!empty($job['device_imei']) || !empty($job['accessories']))
            <tr style="background: #f9fafb;">
                @if(!empty($job['device_imei']))
                <td colspan="2" style="padding: 6px 10px; font-size: 8pt;"><strong style="color: #6b7280;">IMEI:</strong> {{$job['device_imei']}}</td>
                @endif
                @if(!empty($job['accessories']) && is_array($job['accessories']))
                <td colspan="{{ !empty($job['device_imei']) ? '2' : '4' }}" style="padding: 6px 10px; font-size: 8pt;"><strong style="color: #6b7280;">Accessories:</strong> {{ implode(', ', $job['accessories']) }}</td>
                @endif
            </tr>
            @endif
        </tbody>
    </table>
    @endif

    <!-- Reported Issue / Diagnosis -->
    @if(!empty($job['reported_issue']) || !empty($job['diagnosis']) || !empty($job['condition_on_arrival']))
    <table style="width: 100%; margin-bottom: 10px;" cellpadding="0" cellspacing="0">
        @if(!empty($job['condition_on_arrival']))
        <tr><td style="padding: 6px 10px; background: #f9fafb; border-left: 3px solid #1a56db; margin-bottom: 6px;">
            <div style="font-size: 7.5pt; font-weight: bold; color: #6b7280; text-transform: uppercase; margin-bottom: 3px;">Condition on Arrival</div>
            <div style="font-size: 8pt; color: #1f2937; white-space: pre-line;">{{$job['condition_on_arrival']}}</div>
        </td></tr>
        @endif
        @if(!empty($job['reported_issue']))
        <tr><td style="padding: 6px 10px; background: #f9fafb; border-left: 3px solid #f59e0b;">
            <div style="font-size: 7.5pt; font-weight: bold; color: #6b7280; text-transform: uppercase; margin-bottom: 3px;">Reported Issue</div>
            <div style="font-size: 8pt; color: #1f2937; white-space: pre-line;">{{$job['reported_issue']}}</div>
        </td></tr>
        @endif
        @if(!empty($job['diagnosis']))
        <tr><td style="padding: 6px 10px; background: #f9fafb; border-left: 3px solid #10b981;">
            <div style="font-size: 7.5pt; font-weight: bold; color: #6b7280; text-transform: uppercase; margin-bottom: 3px;">Technician Diagnosis</div>
            <div style="font-size: 8pt; color: #1f2937; white-space: pre-line;">{{$job['diagnosis']}}</div>
        </td></tr>
        @endif
    </table>
    @endif

    <!-- Service Job Summary -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px; border: 1px solid #e5e7eb;" cellpadding="0" cellspacing="0">
        <thead>
            <tr style="background: #1a56db;">
                <th style="padding: 6px 10px; text-align: left; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; border-right: 1px solid rgba(255,255,255,0.2);">{{ __('pdf.job_details') }}</th>
                <th style="padding: 6px 10px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase;">{{ __('pdf.value') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-bottom: 1px solid #e5e7eb; background: #ffffff;">
                <td style="padding: 8px 10px; font-weight: 600; font-size: 8.5pt;">{{ __('pdf.service_item') }}</td>
                <td style="padding: 8px 10px; text-align: right; font-size: 8.5pt;">{{$job['service_item']}}</td>
            </tr>
            @if(!empty($job['job_type']))
            <tr style="border-bottom: 1px solid #e5e7eb; background: #f9fafb;">
                <td style="padding: 8px 10px; font-weight: 600; font-size: 8.5pt;">Job Type</td>
                <td style="padding: 8px 10px; text-align: right; font-size: 8.5pt;">{{$job['job_type']}}</td>
            </tr>
            @endif
            <tr style="border-bottom: 1px solid #e5e7eb; background: #ffffff;">
                <td style="padding: 8px 10px; font-weight: 600; font-size: 8.5pt;">{{ __('pdf.technician') }}</td>
                <td style="padding: 8px 10px; text-align: right; font-size: 8.5pt;">{{$job['technician_name']}}</td>
            </tr>
            @if(!empty($job['delivered_at']))
            <tr style="border-bottom: 1px solid #e5e7eb; background: #f9fafb;">
                <td style="padding: 8px 10px; font-weight: 600; font-size: 8.5pt;">Delivered On</td>
                <td style="padding: 8px 10px; text-align: right; font-size: 8.5pt;">{{ \Carbon\Carbon::parse($job['delivered_at'])->format('Y-m-d H:i') }}</td>
            </tr>
            @endif
            @if(!empty($job['warranty_expires_at']))
            <tr style="border-bottom: 1px solid #e5e7eb; background: #ffffff;">
                <td style="padding: 8px 10px; font-weight: 600; font-size: 8.5pt;">Warranty Until</td>
                <td style="padding: 8px 10px; text-align: right; font-size: 8.5pt; color: #065f46; font-weight: bold;">{{ \Carbon\Carbon::parse($job['warranty_expires_at'])->format('Y-m-d') }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <!-- Line Items -->
    @if(!empty($job['items']) && count($job['items']) > 0)
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px; border: 1px solid #e5e7eb;" cellpadding="0" cellspacing="0">
        <thead>
            <tr style="background: #1a56db;">
                <th style="padding: 6px 10px; text-align: left; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; width: 10%;">Type</th>
                <th style="padding: 6px 10px; text-align: left; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase;">Description</th>
                <th style="padding: 6px 10px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; width: 8%;">Qty</th>
                <th style="padding: 6px 10px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; width: 12%;">Unit Price</th>
                <th style="padding: 6px 10px; text-align: right; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; width: 14%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($job['items'] as $idx => $row)
            <tr style="border-bottom: 1px solid #e5e7eb; background: {{$idx % 2 == 0 ? '#ffffff' : '#f9fafb'}};">
                <td style="padding: 6px 10px; font-size: 8pt; text-transform: uppercase; color: #6b7280;">{{ $row['type'] ?? 'part' }}</td>
                <td style="padding: 6px 10px; font-size: 8.5pt;">{{ $row['description'] }}</td>
                <td style="padding: 6px 10px; font-size: 8.5pt; text-align: right;">{{ $row['quantity'] }}</td>
                <td style="padding: 6px 10px; font-size: 8.5pt; text-align: right;">{{ $cur }}{{ number_format((float)$row['unit_price'], 2) }}</td>
                <td style="padding: 6px 10px; font-size: 8.5pt; text-align: right; font-weight: bold;">{{ $cur }}{{ number_format((float)$row['total'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            @if(!empty($job['diagnostic_fee']) && (float)$job['diagnostic_fee'] > 0)
            <tr style="background: #f9fafb;">
                <td colspan="4" style="padding: 6px 10px; font-size: 8.5pt; text-align: right;">Diagnostic Fee</td>
                <td style="padding: 6px 10px; font-size: 8.5pt; text-align: right;">{{ $cur }}{{ number_format((float)$job['diagnostic_fee'], 2) }}</td>
            </tr>
            @endif
            <tr style="background: #1a56db; color: #ffffff;">
                <td colspan="4" style="padding: 8px 10px; font-size: 9pt; text-align: right; font-weight: bold;">GRAND TOTAL</td>
                <td style="padding: 8px 10px; font-size: 10pt; text-align: right; font-weight: bold;">{{ $cur }}{{ number_format((float)$job['total_amount'], 2) }}</td>
            </tr>
            @if((float)($job['paid_amount'] ?? 0) > 0)
            <tr style="background: #d1fae5;">
                <td colspan="4" style="padding: 6px 10px; font-size: 8.5pt; text-align: right; color: #065f46; font-weight: bold;">Paid</td>
                <td style="padding: 6px 10px; font-size: 8.5pt; text-align: right; color: #065f46; font-weight: bold;">{{ $cur }}{{ number_format((float)$job['paid_amount'], 2) }}</td>
            </tr>
            <tr style="background: {{ ((float)$job['total_amount'] - (float)$job['paid_amount']) > 0 ? '#fee2e2' : '#d1fae5' }};">
                <td colspan="4" style="padding: 6px 10px; font-size: 8.5pt; text-align: right; font-weight: bold;">Balance Due</td>
                <td style="padding: 6px 10px; font-size: 8.5pt; text-align: right; font-weight: bold;">{{ $cur }}{{ number_format((float)$job['total_amount'] - (float)$job['paid_amount'], 2) }}</td>
            </tr>
            @endif
        </tfoot>
    </table>
    @endif

    <!-- Payments -->
    @if(!empty($job['payments']) && count($job['payments']) > 0)
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px; border: 1px solid #e5e7eb;" cellpadding="0" cellspacing="0">
        <thead>
            <tr style="background: #1a56db;">
                <th colspan="5" style="padding: 6px 10px; text-align: left; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase;">Payment History</th>
            </tr>
            <tr style="background: #f3f4f6;">
                <th style="padding: 6px 10px; text-align: left; font-size: 7.5pt; font-weight: bold; color: #1f2937;">Reference</th>
                <th style="padding: 6px 10px; text-align: left; font-size: 7.5pt; font-weight: bold; color: #1f2937;">Date</th>
                <th style="padding: 6px 10px; text-align: left; font-size: 7.5pt; font-weight: bold; color: #1f2937;">Kind</th>
                <th style="padding: 6px 10px; text-align: left; font-size: 7.5pt; font-weight: bold; color: #1f2937;">Method</th>
                <th style="padding: 6px 10px; text-align: right; font-size: 7.5pt; font-weight: bold; color: #1f2937;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($job['payments'] as $idx => $p)
            <tr style="border-bottom: 1px solid #e5e7eb; background: {{$idx % 2 == 0 ? '#ffffff' : '#f9fafb'}};">
                <td style="padding: 6px 10px; font-size: 8pt;">{{ $p['Ref'] }}</td>
                <td style="padding: 6px 10px; font-size: 8pt;">{{ $p['date'] }}</td>
                <td style="padding: 6px 10px; font-size: 8pt; text-transform: capitalize;">{{ $p['payment_kind'] ?? 'payment' }}</td>
                <td style="padding: 6px 10px; font-size: 8pt;">{{ $p['payment_method'] ?? '-' }}</td>
                <td style="padding: 6px 10px; font-size: 8pt; text-align: right;">{{ $cur }}{{ number_format((float)$p['montant'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- Checklist Section -->
    @if(!empty($job['checklist']) && count($job['checklist']) > 0)
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px; border: 1px solid #e5e7eb;" cellpadding="0" cellspacing="0">
        <thead>
            <tr style="background: #1a56db;">
                <th style="padding: 6px 10px; text-align: left; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; width: 5%;">✓</th>
                <th style="padding: 6px 10px; text-align: left; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase; width: 30%;">Category</th>
                <th style="padding: 6px 10px; text-align: left; font-size: 8pt; font-weight: bold; color: #ffffff; text-transform: uppercase;">Item</th>
            </tr>
        </thead>
        <tbody>
            @foreach($job['checklist'] as $index => $item)
            <tr style="border-bottom: 1px solid #e5e7eb; background: {{$index % 2 == 0 ? '#ffffff' : '#f9fafb'}};">
                <td style="padding: 8px 10px; text-align: center; font-size: 9pt; color: {{$item['is_completed'] ? '#10b981' : '#9ca3af'}};">
                    {{$item['is_completed'] ? '✓' : '○'}}
                </td>
                <td style="padding: 8px 10px; font-size: 8pt; color: #6b7280;">{{$item['category_name'] ?? '-'}}</td>
                <td style="padding: 8px 10px; font-size: 8.5pt; {{$item['is_completed'] ? 'text-decoration: line-through;' : ''}}">{{$item['item_name']}}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- Notes -->
    @if(!empty($job['notes']))
    <div style="margin-top: 10px; padding: 10px; background: #f9fafb; border-left: 3px solid #1a56db; border-radius: 3px;">
        <div style="font-size: 8pt; font-weight: bold; color: #1f2937; margin-bottom: 5px; text-transform: uppercase;">NOTES</div>
        <div style="font-size: 8pt; color: #6b7280; line-height: 1.5; white-space: pre-line;">{{$job['notes']}}</div>
    </div>
    @endif

    <!-- Pickup acknowledgment / signature -->
    @if(!empty($job['delivered_at']))
    <table style="width: 100%; margin-top: 14px;" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 60%; padding: 8px 10px; font-size: 7.5pt; color: #6b7280; line-height: 1.5;">
                I acknowledge receipt of the device in working condition. Warranty period: {{ $job['warranty_days'] ?? 30 }} days
                @if(!empty($job['warranty_expires_at']))
                    (until {{ \Carbon\Carbon::parse($job['warranty_expires_at'])->format('Y-m-d') }})
                @endif.
            </td>
            <td style="width: 40%; padding: 8px 10px;">
                <div style="border-bottom: 1px solid #1f2937; height: 30px;"></div>
                <div style="font-size: 7.5pt; color: #6b7280; text-align: center; margin-top: 4px;">Customer signature{{ !empty($job['pickup_signature']) ? ' — '.$job['pickup_signature'] : '' }}</div>
            </td>
        </tr>
    </table>
    @endif

    <!-- Footer -->
    <div style="margin-top: 15px; padding-top: 10px; border-top: 2px solid #e5e7eb;">
        @if(!empty($setting['is_invoice_footer']) && !empty($setting['invoice_footer']))
            <div style="padding: 8px 10px; background: #f9fafb; border-left: 3px solid #1a56db; border-radius: 3px; margin-bottom: 10px;">
                <p style="font-size: 7.5pt; color: #6b7280; line-height: 1.5; margin: 0;">{{$setting['invoice_footer']}}</p>
            </div>
        @endif
        <div style="text-align: center; padding: 8px 0;">
            <p style="font-size: 10pt; font-weight: bold; color: #1a56db; margin: 0;">Thank you for your business!</p>
        </div>
    </div>
</body>
</html>
