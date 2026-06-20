@php $pdfLocale = app()->getLocale(); $isRtl = $pdfLocale === 'ar'; @endphp
<!DOCTYPE html>
<html lang="{{ $pdfLocale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>System Health Report</title>
    <style>
        @page { size: A4; margin: 10mm 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, body * { font-family: 'DejaVu Sans', sans-serif !important; }
        body { font-size: 9pt; color: #1f2937; line-height: 1.4; padding: 15px 20px; max-width: 100%; }
        body.rtl { direction: rtl; text-align: right; }
        .header-title { font-size: 18pt; font-weight: bold; color: #6366f1; margin-bottom: 4px; }
        .divider { height: 2px; background: #6366f1; margin: 10px 0 14px 0; }
        table.metrics { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.metrics th, table.metrics td { border: 1px solid #e5e7eb; padding: 8px 10px; text-align: left; }
        body.rtl table.metrics th, body.rtl table.metrics td { text-align: right; }
        table.metrics th { background: #6366f1; color: #fff; font-weight: bold; }
        table.metrics tr:nth-child(even) { background: #f9fafb; }
        .footer { margin-top: 16px; font-size: 8pt; color: #6b7280; }
    </style>
</head>
<body class="{{ $isRtl ? 'rtl' : '' }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
    <div class="header-title">{{ $companyName }} — System Health Report</div>
    <div class="footer">Generated: {{ $generated_at }}</div>
    <div class="divider"></div>

    <table class="metrics" cellpadding="0" cellspacing="0">
        <tr>
            <th style="width: 38%;">Metric</th>
            <th>Value</th>
        </tr>
        <tr>
            <td><strong>PHP Version</strong></td>
            <td>{{ $metrics['php_version'] ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Laravel Version</strong></td>
            <td>{{ $metrics['laravel_version'] ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Environment</strong></td>
            <td>{{ $metrics['environment'] ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Database size</strong></td>
            <td>{{ $metrics['database']['size_human'] ?? ($metrics['database']['error'] ?? '—') }}</td>
        </tr>
        <tr>
            <td><strong>Storage usage</strong></td>
            <td>{{ $metrics['storage']['size_human'] ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Queue driver</strong></td>
            <td>{{ $metrics['queue']['driver'] ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Queue pending</strong></td>
            <td>{{ isset($metrics['queue']['pending']) ? (int)$metrics['queue']['pending'] : '—' }}</td>
        </tr>
        <tr>
            <td><strong>Queue failed</strong></td>
            <td>{{ isset($metrics['queue']['failed']) ? (int)$metrics['queue']['failed'] : '—' }}</td>
        </tr>
        <tr>
            <td><strong>Last backup</strong></td>
            <td>{{ $metrics['last_backup']['human'] ?? ($metrics['last_backup']['message'] ?? '—') }}</td>
        </tr>
    </table>

    <div class="footer">This report contains non-sensitive system metrics only.</div>
</body>
</html>
