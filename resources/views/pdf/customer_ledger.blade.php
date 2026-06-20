@php $pdfLocale = app()->getLocale(); $isRtl = $pdfLocale === 'ar'; @endphp
<!doctype html>
<html lang="{{ $pdfLocale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
  <meta charset="utf-8">
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>{{ __('pdf.customer_ledger') }}</title>
  @php
    // Price formatting helper function
    $priceFormat = $settings->price_format ?? null;
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
    /* Page + Base */
    @page { margin: 22px; }
    body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
    .wrap { page-break-inside: avoid; }

    /* Header */
    .header {
      padding: 12px 14px;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      background: #f8fafc;
      margin-bottom: 14px;
    }
    .h-top { display: table; width: 100%; }
    .h-left, .h-right { display: table-cell; vertical-align: top; }
    .h-right { text-align: right; }
    .title { font-size: 20px; font-weight: 700; color: #111827; }
    .muted { color: #6b7280; }

    .client-box {
      margin-top: 8px; padding: 10px 12px;
      border: 1px dashed #d1d5db; border-radius: 8px; background: #ffffff;
    }
    .client-col { display: table; width: 100%; }
    .client-left, .client-right { display: table-cell; vertical-align: top; }
    .client-right { text-align: right; }

    /* KPI / Quick Stats */
    .stats {
      margin: 10px 0 14px;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 10px;
      background: #ffffff;
    }
    .kpi-grid { display: table; width: 100%; border-spacing: 8px 6px; }
    .kpi {
      display: table-cell; width: 33%;
      border: 1px solid #eef2f7; border-radius: 10px;
      background: #f9fafb; padding: 10px;
    }
    .kpi .label { font-size: 11px; color: #6b7280; }
    .kpi .value { font-size: 16px; font-weight: 800; color: #111827; }
    .danger { color: #dc2626; }
    .warning { color: #d97706; }
    .success { color: #16a34a; }

    /* Section heading */
    h3 {
      margin: 18px 0 8px; font-size: 14px; color: #0f172a;
      border-left: 4px solid #3b82f6; padding-left: 8px;
    }

    /* Tables */
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { border: 1px solid #e5e7eb; padding: 6px 6px; vertical-align: middle; word-wrap: break-word; }
    thead th { font-size: 12px; text-align: left; background: #f3f4f6; color: #111827; }
    tbody tr:nth-child(odd) { background: #fafafa; }
    .right { text-align: right; }
    tfoot td { font-weight: 700; background: #f8fafc; }

    /* Badges (statuses) */
    .pill {
      display: inline-block; padding: 2px 8px; border-radius: 999px;
      font-size: 10px; font-weight: 700; border: 1px solid #e5e7eb; color: #374151; background: #f9fafb;
    }
    .pill.success { border-color: #bae6fd; color: #065f46; background: #ecfdf5; }
    .pill.warn    { border-color: #fde68a; color: #92400e; background: #fffbeb; }
    .pill.info    { border-color: #bfdbfe; color: #1e3a8a; background: #eff6ff; }
    .pill.danger  { border-color: #fecaca; color: #7f1d1d; background: #fef2f2; }

    /* Break control */
    .avoid-break { page-break-inside: avoid; }
  </style>
</head>
<body class="{{ $isRtl ? 'rtl' : '' }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">

  @php
    // ---------- Helper: status badges (order/workflow) ----------
    function pillSale($status) {
      $s = strtolower(trim((string)$status));
      if (preg_match('/\b(completed|approved|fulfilled|closed)\b/', $s)) return 'pill success';
      if (preg_match('/\b(pending|awaiting|on hold)\b/', $s))        return 'pill warn';
      if (preg_match('/\b(sent|shipped|in transit)\b/', $s))         return 'pill info';
      if (preg_match('/\b(canceled|cancelled|rejected|void)\b/', $s))return 'pill danger';
      return 'pill';
    }

    // ---------- Helper: payment badges (avoid "unpaid" -> "paid") ----------
    function pillPayment($status) {
      $s = strtolower(trim((string)$status));
      if (preg_match('/\b(unpaid|not\s*paid|overdue|failed|due)\b/', $s)) return 'pill danger';
      if (preg_match('/\b(partial|partially\s*paid|part-?paid)\b/', $s))  return 'pill warn';
      if (preg_match('/\b(paid|settled|paid\s*in\s*full)\b/', $s))        return 'pill success';
      return 'pill';
    }

    // ---------- Section totals ----------
    $salesGrandSum = $sales->sum('GrandTotal');
    $salesPaidSum  = $sales->sum('paid_amount');
    $salesDueSum   = $sales->sum(function($s){ return (float)($s->GrandTotal ?? 0) - (float)($s->paid_amount ?? 0); });

    $paymentsSum   = $payments->sum('montant');

    $quotesGrand   = $quotations->sum('GrandTotal');

    $retGrandSum   = $returns->sum('GrandTotal');
    $retPaidSum    = $returns->sum('paid_amount');
    $retDueSum     = $returns->sum(function($r){ return (float)($r->GrandTotal ?? 0) - (float)($r->paid_amount ?? 0); });

    // ---------- Safe fallbacks for quick stats ----------
    $qsOpeningBalance = (float)($client->opening_balance ?? 0);
    $qsSalesGrand   = (float)($client->salesGrand ?? $salesGrandSum);
    $qsSalesPaid    = (float)($client->salesPaid  ?? $salesPaidSum);
    $qsSaleDue      = (float)($client->sale_due   ?? ($qsSalesGrand - $qsSalesPaid));

    $qsReturnsDue   = (float)($client->return_due ?? $retDueSum);
    $qsPaymentsTot  = (float)($client->paymentsTotal ?? $paymentsSum);
    $qsQuotesGrand  = (float)($client->quotationsTotal ?? $quotesGrand);

    $qsNetBalance   = isset($client->netBalance)
                      ? (float)$client->netBalance
                      : ($qsOpeningBalance + $qsSaleDue - $qsReturnsDue);
  @endphp

  <!-- HEADER -->
  <div class="header wrap">
    <div class="h-top">
      <div class="h-left">
        <div class="title">{{ __('pdf.customer_ledger') }}</div>
        <div class="muted">Generated at: {{ now()->format('Y-m-d H:i') }}</div>
      </div>
      <div class="h-right">
        {{-- (Optional) Logo / Company Name --}}
      </div>
    </div>

    <div class="client-box">
      <div class="client-col">
        <div class="client-left">
          <strong>{{ $client->name }}</strong><br>
          Code: {{ $client->code ?? '-' }}<br>
          City: {{ $client->city ?? '-' }}, Country: {{ $client->country ?? '-' }}
        </div>
        <div class="client-right">
          Email: {{ $client->email ?? '-' }}<br>
          Phone: {{ $client->phone ?? '-' }}<br>
          Tax #: {{ $client->tax_number ?? '-' }}
        </div>
      </div>
    </div>
  </div>

  <!-- QUICK STATS -->
  <div class="stats wrap">
    <div class="kpi-grid">
      <div class="kpi">
        <div class="label">{{ __('pdf.opening_balance') }}</div>
        <div class="value {{ $qsOpeningBalance > 0 ? 'danger' : '' }}">{{ formatPrice($qsOpeningBalance, 2, $priceFormat) }}</div>
      </div>
      <div class="kpi">
        <div class="label">Sales (Grand)</div>
        <div class="value">{{ formatPrice($qsSalesGrand, 2, $priceFormat) }}</div>
      </div>
      <div class="kpi">
        <div class="label">Sales (Paid)</div>
        <div class="value">{{ formatPrice($qsSalesPaid, 2, $priceFormat) }}</div>
      </div>
      <div class="kpi">
        <div class="label">Sales (Due)</div>
        <div class="value danger">{{ formatPrice($qsSaleDue, 2, $priceFormat) }}</div>
      </div>

      <div class="kpi">
        <div class="label">Returns (Due)</div>
        <div class="value warning">{{ formatPrice($qsReturnsDue, 2, $priceFormat) }}</div>
      </div>
      <div class="kpi">
        <div class="label">Payments (Total)</div>
        <div class="value">{{ formatPrice($qsPaymentsTot, 2, $priceFormat) }}</div>
      </div>
      <div class="kpi">
        <div class="label">Quotations (Grand)</div>
        <div class="value">{{ formatPrice($qsQuotesGrand, 2, $priceFormat) }}</div>
      </div>

      <div class="kpi">
        <div class="label">{{ __('pdf.net_balance') }}</div>
        <div class="value {{ $qsNetBalance >= 0 ? 'danger' : 'success' }}">
          {{ formatPrice($qsNetBalance, 2, $priceFormat) }}
        </div>
      </div>
    </div>
  </div>

  <!-- OPENING BALANCE -->
  @if($qsOpeningBalance != 0)
  <h3>Opening Balance (Previous Dues)</h3>
  <table class="avoid-break">
    <thead>
      <tr>
        <th style="width:20%;">{{ __('pdf.type') }}</th>
        <th style="width:20%;">{{ __('pdf.description') }}</th>
        <th class="right" style="width:20%;">{{ __('pdf.amount') }}</th>
        <th style="width:40%;">{{ __('pdf.notes') }}</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>{{ __('pdf.opening_balance') }}</td>
        <td>Previous Dues (Before System Start)</td>
        <td class="right {{ $qsOpeningBalance > 0 ? 'danger' : '' }}">{{ formatPrice($qsOpeningBalance, 2, $priceFormat) }}</td>
        <td>{{ __('pdf.balance_carried_forward') }}</td>
      </tr>
    </tbody>
  </table>
  @endif

  <!-- SALES -->
  <h3>{{ __('pdf.sales') }}</h3>
  <table class="avoid-break">
    <thead>
      <tr>
        <th style="width:12%;">{{ __('pdf.date') }}</th>
        <th style="width:14%;">{{ __('pdf.ref') }}</th>
        <th style="width:18%;">{{ __('pdf.warehouse') }}</th>
        <th style="width:12%;">{{ __('pdf.status') }}</th>
        <th class="right" style="width:14%;">{{ __('pdf.grand_total') }}</th>
        <th class="right" style="width:14%;">{{ __('pdf.paid') }}</th>
        <th class="right" style="width:14%;">{{ __('pdf.due') }}</th>
        <th style="width:12%;">{{ __('pdf.payment_status') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($sales as $s)
      <tr>
        <td>
            @php
                $dateFormat = $settings->date_format ?? 'YYYY-MM-DD';
                $dateTime = \Carbon\Carbon::parse($s->date);
                $phpDateFormat = str_replace(['YYYY', 'MM', 'DD'], ['Y', 'm', 'd'], $dateFormat);
                // Check if original date string contains time
                $hasTime = strpos($s->date, ' ') !== false && preg_match('/\d{1,2}:\d{2}/', $s->date);
                if ($hasTime) {
                    $formattedDate = $dateTime->format($phpDateFormat . ' H:i');
                    // Preserve seconds if they exist
                    if (preg_match('/:\d{2}:\d{2}/', $s->date)) {
                        $formattedDate = $dateTime->format($phpDateFormat . ' H:i:s');
                    }
                } else {
                    $formattedDate = $dateTime->format($phpDateFormat);
                }
            @endphp
            {{$formattedDate}}
        </td>
        <td>{{ $s->Ref }}</td>
        <td>{{ optional($s->warehouse)->name }}</td>
        <td><span class="{{ pillSale($s->statut) }}">{{ $s->statut }}</span></td>
        <td class="right">{{ formatPrice($s->GrandTotal, 2, $priceFormat) }}</td>
        <td class="right">{{ formatPrice($s->paid_amount, 2, $priceFormat) }}</td>
        <td class="right">{{ formatPrice(($s->GrandTotal - $s->paid_amount), 2, $priceFormat) }}</td>
        <td><span class="{{ pillPayment($s->payment_statut) }}">{{ $s->payment_statut }}</span></td>
      </tr>
      @empty
      <tr><td colspan="8">No sales found.</td></tr>
      @endforelse
    </tbody>
    <tfoot>
      <tr>
        <td colspan="4" class="right">{{ __('pdf.totals') }}</td>
        <td class="right">{{ formatPrice($salesGrandSum, 2, $priceFormat) }}</td>
        <td class="right">{{ formatPrice($salesPaidSum, 2, $priceFormat) }}</td>
        <td class="right">{{ formatPrice($salesDueSum, 2, $priceFormat) }}</td>
        <td></td>
      </tr>
    </tfoot>
  </table>

  <!-- PAYMENTS -->
  <h3>{{ __('pdf.payments') }}</h3>
  <table class="avoid-break">
    <thead>
      <tr>
        <th style="width:14%;">{{ __('pdf.date') }}</th>
        <th style="width:16%;">{{ __('pdf.payment_ref') }}</th>
        <th style="width:12%;">{{ __('pdf.type') }}</th>
        <th style="width:16%;">{{ __('pdf.sale_ref') }}</th>
        <th style="width:18%;">{{ __('pdf.method') }}</th>
        <th class="right" style="width:18%;">{{ __('pdf.amount') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($payments as $p)
      <tr>
        <td>
            @php
                $dateFormat = $settings->date_format ?? 'YYYY-MM-DD';
                $dateTime = \Carbon\Carbon::parse($p->date);
                $phpDateFormat = str_replace(['YYYY', 'MM', 'DD'], ['Y', 'm', 'd'], $dateFormat);
                // Check if original date string contains time
                $hasTime = strpos($p->date, ' ') !== false && preg_match('/\d{1,2}:\d{2}/', $p->date);
                if ($hasTime) {
                    $formattedDate = $dateTime->format($phpDateFormat . ' H:i');
                    // Preserve seconds if they exist
                    if (preg_match('/:\d{2}:\d{2}/', $p->date)) {
                        $formattedDate = $dateTime->format($phpDateFormat . ' H:i:s');
                    }
                } else {
                    $formattedDate = $dateTime->format($phpDateFormat);
                }
            @endphp
            {{$formattedDate}}
        </td>
        <td>{{ $p->Ref }}</td>
        <td>
          @if(isset($p->payment_type) && $p->payment_type === 'opening_balance')
            <span class="pill info">{{ __('pdf.opening_balance') }}</span>
          @else
            <span class="pill success">{{ __('pdf.sale') }}</span>
          @endif
        </td>
        <td>{{ $p->Sale_Ref ?? '-' }}</td>
        <td>{{ $p->payment_method }}</td>
        <td class="right">{{ formatPrice($p->montant, 2, $priceFormat) }}</td>
      </tr>
      @empty
      <tr><td colspan="6">No payments found.</td></tr>
      @endforelse
    </tbody>
    <tfoot>
      <tr>
        <td colspan="5" class="right">Total Payments</td>
        <td class="right">{{ formatPrice($paymentsSum, 2, $priceFormat) }}</td>
      </tr>
    </tfoot>
  </table>

  <!-- QUOTATIONS -->
  <h3>{{ __('pdf.quotations') }}</h3>
  <table class="avoid-break">
    <thead>
      <tr>
        <th style="width:16%;">{{ __('pdf.date') }}</th>
        <th style="width:20%;">{{ __('pdf.ref') }}</th>
        <th style="width:18%;">{{ __('pdf.status') }}</th>
        <th style="width:28%;">{{ __('pdf.warehouse') }}</th>
        <th class="right" style="width:18%;">{{ __('pdf.grand_total') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($quotations as $q)
      <tr>
        <td>
            @php
                $dateFormat = $settings->date_format ?? 'YYYY-MM-DD';
                $dateTime = \Carbon\Carbon::parse($q->date);
                $phpDateFormat = str_replace(['YYYY', 'MM', 'DD'], ['Y', 'm', 'd'], $dateFormat);
                // Check if original date string contains time
                $hasTime = strpos($q->date, ' ') !== false && preg_match('/\d{1,2}:\d{2}/', $q->date);
                if ($hasTime) {
                    $formattedDate = $dateTime->format($phpDateFormat . ' H:i');
                    // Preserve seconds if they exist
                    if (preg_match('/:\d{2}:\d{2}/', $q->date)) {
                        $formattedDate = $dateTime->format($phpDateFormat . ' H:i:s');
                    }
                } else {
                    $formattedDate = $dateTime->format($phpDateFormat);
                }
            @endphp
            {{$formattedDate}}
        </td>
        <td>{{ $q->Ref }}</td>
        <td><span class="{{ pillSale($q->statut) }}">{{ $q->statut }}</span></td>
        <td>{{ optional($q->warehouse)->name }}</td>
        <td class="right">{{ formatPrice($q->GrandTotal, 2, $priceFormat) }}</td>
      </tr>
      @empty
      <tr><td colspan="5">No quotations found.</td></tr>
      @endforelse
    </tbody>
    <tfoot>
      <tr>
        <td colspan="4" class="right">{{ __('pdf.total_quotations') }}</td>
        <td class="right">{{ formatPrice($quotesGrand, 2, $priceFormat) }}</td>
      </tr>
    </tfoot>
  </table>

  <!-- RETURNS -->
  <h3>{{ __('pdf.returns') }}</h3>
  <table class="avoid-break">
    <thead>
      <tr>
        <th style="width:16%;">{{ __('pdf.ref') }}</th>
        <th style="width:14%;">{{ __('pdf.status') }}</th>
        <th style="width:18%;">{{ __('pdf.sale_ref') }}</th>
        <th style="width:24%;">{{ __('pdf.warehouse') }}</th>
        <th class="right" style="width:12%;">{{ __('pdf.grand_total') }}</th>
        <th class="right" style="width:8%;">{{ __('pdf.paid') }}</th>
        <th class="right" style="width:8%;">{{ __('pdf.due') }}</th>
        <th style="width:12%;">{{ __('pdf.payment_status') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($returns as $r)
      <tr>
        <td>{{ $r->Ref }}</td>
        <td><span class="{{ pillSale($r->statut) }}">{{ $r->statut }}</span></td>
        <td>{{ optional($r->sale)->Ref ?? '---' }}</td>
        <td>{{ optional($r->warehouse)->name }}</td>
        <td class="right">{{ number_format($r->GrandTotal, 2) }}</td>
        <td class="right">{{ number_format($r->paid_amount, 2) }}</td>
        <td class="right">{{ number_format(($r->GrandTotal - $r->paid_amount), 2) }}</td>
        <td><span class="{{ pillPayment($r->payment_statut) }}">{{ $r->payment_statut }}</span></td>
      </tr>
      @empty
      <tr><td colspan="8">No returns found.</td></tr>
      @endforelse
    </tbody>
    <tfoot>
      <tr>
        <td colspan="4" class="right">{{ __('pdf.totals') }}</td>
        <td class="right">{{ formatPrice($retGrandSum, 2, $priceFormat) }}</td>
        <td class="right">{{ formatPrice($retPaidSum, 2, $priceFormat) }}</td>
        <td class="right">{{ formatPrice($retDueSum, 2, $priceFormat) }}</td>
        <td></td>
      </tr>
    </tfoot>
  </table>

</body>
</html>
