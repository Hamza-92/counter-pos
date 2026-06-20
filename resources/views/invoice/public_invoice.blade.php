<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $sale->Ref }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            color: #333;
            line-height: 1.5;
            padding: 20px;
        }
        .invoice-container {
            max-width: 500px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            padding: 30px 24px;
        }
        .header { text-align: center; margin-bottom: 16px; }
        .header .logo { max-height: 70px; max-width: 200px; margin-bottom: 8px; }
        .company-name-ar { font-size: 18px; font-weight: 700; }
        .company-name-en { font-size: 16px; font-weight: 700; color: #555; }
        .vat-number { font-size: 12px; color: #666; margin-top: 4px; }
        .tax-title {
            border-top: 2px dashed #333;
            border-bottom: 2px dashed #333;
            padding: 6px 0;
            margin: 12px 0;
            text-align: center;
            font-weight: 700;
            font-size: 14px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            padding: 2px 0;
        }
        .info-row .label-en { text-align: left; }
        .info-row .value { text-align: center; font-weight: 600; }
        .info-row .label-ar { text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        .items-table { margin-top: 10px; font-size: 12px; border-top: 2px dashed #333; }
        .items-table th {
            padding: 6px 4px;
            text-align: left;
            font-size: 11px;
            border-bottom: 1px solid #ddd;
        }
        .items-table th:last-child { text-align: right; }
        .items-table th:nth-child(2), .items-table th:nth-child(3) { text-align: center; }
        .items-table td { padding: 5px 4px; border-bottom: 1px dashed #eee; }
        .items-table td:last-child { text-align: right; }
        .items-table td:nth-child(2), .items-table td:nth-child(3) { text-align: center; }
        .totals-table { font-size: 12px; border-top: 2px dashed #333; margin-top: 6px; }
        .totals-table td { padding: 3px 4px; }
        .totals-table .label { font-weight: 600; }
        .grand-total-table {
            font-size: 13px;
            font-weight: 700;
            border-top: 2px dashed #333;
            border-bottom: 2px dashed #333;
            margin-top: 4px;
        }
        .grand-total-table td { padding: 6px 4px; }
        .payments-table { font-size: 11px; margin-top: 10px; }
        .payments-table thead tr { background: #f5f5f5; }
        .payments-table th, .payments-table td { padding: 4px 6px; }
        .footer { margin-top: 16px; text-align: center; font-size: 11px; color: #888; }
        .badge-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 6px;
        }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-partial { background: #fff3cd; color: #856404; }
        .badge-unpaid { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="invoice-container">
        {{-- Header --}}
        <div class="header">
            @if(!empty($setting->logo))
                <img src="{{ asset('images/' . $setting->logo) }}" alt="Logo" class="logo"><br>
            @endif
            @if(!empty($setting->company_name_ar))
                <div class="company-name-ar">{{ $setting->company_name_ar }}</div>
            @endif
            <div class="company-name-en">{{ $setting->CompanyName }}</div>
            @if(!empty($setting->CompanyAdress))
                <div style="font-size:12px;color:#666;">{{ $setting->CompanyAdress }}</div>
            @endif
            @if(!empty($setting->CompanyPhone))
                <div style="font-size:12px;color:#666;">{{ $setting->CompanyPhone }}</div>
            @endif
            @if(!empty($setting->email))
                <div style="font-size:12px;color:#666;">{{ $setting->email }}</div>
            @endif
            @if(!empty($setting->vat_number))
                <div class="vat-number">الرقم الضريبي / Vat No : {{ $setting->vat_number }}</div>
            @endif
        </div>

        <div class="tax-title">
            فاتورة ضريبية مبسطة<br>
            Simplified Tax Invoice
        </div>

        {{-- Info Rows --}}
        <div>
            <div class="info-row">
                <span class="label-en">Invoice No</span>
                <span class="value">{{ $sale->Ref }}</span>
                <span class="label-ar">رقم الفاتورة</span>
            </div>
            <div class="info-row">
                <span class="label-en">Date</span>
                <span class="value">{{ $sale->date }} {{ $sale->time }}</span>
                <span class="label-ar">تاريخ</span>
            </div>
            @if($sale->user)
            <div class="info-row">
                <span class="label-en">Seller</span>
                <span class="value">{{ $sale->user->username }}</span>
                <span class="label-ar">البائع</span>
            </div>
            @endif
            @if($sale->client)
            <div class="info-row">
                <span class="label-en">Customer</span>
                <span class="value">{{ $sale->client->name }}</span>
                <span class="label-ar">العميل</span>
            </div>
            @endif
            @if($sale->warehouse)
            <div class="info-row">
                <span class="label-en">Warehouse</span>
                <span class="value">{{ $sale->warehouse->name }}</span>
                <span class="label-ar">المستودع</span>
            </div>
            @endif
        </div>

        {{-- Items --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th>Product / المنتج</th>
                    <th style="text-align:center">Qty / كمية</th>
                    <th style="text-align:center">Rate / معدل</th>
                    <th style="text-align:right">Amount / مجموع</th>
                </tr>
            </thead>
            <tbody>
                @foreach($details as $d)
                <tr>
                    <td>{{ $d['name'] }}</td>
                    <td>{{ number_format($d['quantity'], 2) }} {{ $d['unit_sale'] }}</td>
                    <td>{{ number_format($d['total'] / max($d['quantity'], 1), 2) }}</td>
                    <td>{{ number_format($d['total'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totals --}}
        @php
            $subtotal = collect($details)->sum('total');
            $taxNet = (float) $sale->TaxNet;
            $discount = (float) $sale->discount;
            $discountMethod = $sale->discount_Method ?? '2';
            $shipping = (float) $sale->shipping;
            $grandTotal = (float) $sale->GrandTotal;
            $paidAmount = (float) $sale->paid_amount;
            $due = $grandTotal - $paidAmount;

            if ($discountMethod === '1') {
                $discountAmount = ($subtotal + $taxNet + $shipping) * $discount / (100 + $discount);
            } else {
                $discountAmount = $discount;
            }
        @endphp

        <table class="totals-table">
            <colgroup><col style="width:35%"><col style="width:5%"><col style="width:25%"><col style="width:35%"></colgroup>
            <tbody>
                <tr>
                    <td class="label">Sub Total</td>
                    <td>:</td>
                    <td style="text-align:center">{{ $symbol }} {{ number_format($subtotal, 2) }}</td>
                    <td style="text-align:right" class="label">المجموع الفرعي</td>
                </tr>
                @if($taxNet > 0)
                <tr>
                    <td class="label">VAT</td>
                    <td>:</td>
                    <td style="text-align:center">{{ $symbol }} {{ number_format($taxNet, 2) }}</td>
                    <td style="text-align:right" class="label">قيمة الضريبة</td>
                </tr>
                @endif
                @if($discountAmount > 0)
                <tr>
                    <td class="label">Discount</td>
                    <td>:</td>
                    <td style="text-align:center">{{ $symbol }} {{ number_format($discountAmount, 2) }}</td>
                    <td style="text-align:right" class="label">تخفيض</td>
                </tr>
                @endif
                @if($shipping > 0)
                <tr>
                    <td class="label">Shipping</td>
                    <td>:</td>
                    <td style="text-align:center">{{ $symbol }} {{ number_format($shipping, 2) }}</td>
                    <td style="text-align:right" class="label">الشحن</td>
                </tr>
                @endif
            </tbody>
        </table>

        <table class="grand-total-table">
            <colgroup><col style="width:35%"><col style="width:5%"><col style="width:25%"><col style="width:35%"></colgroup>
            <tr>
                <td>Grand Total</td>
                <td>:</td>
                <td style="text-align:center">{{ $symbol }} {{ number_format($grandTotal, 2) }}</td>
                <td style="text-align:right">المبلغ الإجمالي</td>
            </tr>
        </table>

        <table class="totals-table" style="margin-top:4px;">
            <colgroup><col style="width:35%"><col style="width:5%"><col style="width:25%"><col style="width:35%"></colgroup>
            <tr>
                <td class="label">Paid Amount</td>
                <td>:</td>
                <td style="text-align:center">{{ $symbol }} {{ number_format($paidAmount, 2) }}</td>
                <td style="text-align:right" class="label">المبلغ المدفوع</td>
            </tr>
            @if($due > 0.01)
            <tr>
                <td class="label">Balance</td>
                <td>:</td>
                <td style="text-align:center">{{ $symbol }} {{ number_format($due, 2) }}</td>
                <td style="text-align:right" class="label">الرصيد</td>
            </tr>
            @endif
        </table>

        {{-- Payment Status Badge --}}
        <div style="text-align:center;margin-top:10px;">
            @if($due <= 0.01)
                <span class="badge-status badge-paid">Paid / مدفوع</span>
            @elseif($paidAmount > 0)
                <span class="badge-status badge-partial">Partial / جزئي</span>
            @else
                <span class="badge-status badge-unpaid">Unpaid / غير مدفوع</span>
            @endif
        </div>

        {{-- Payments --}}
        @if($payments->count() > 0)
        <table class="payments-table">
            <thead>
                <tr>
                    <th style="text-align:left">Paid By / طريقة الدفع</th>
                    <th style="text-align:center">Amount / المبلغ</th>
                    <th style="text-align:right">Change / الباقي</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payments as $p)
                <tr>
                    <td style="text-align:left">{{ $p->payment_method ? $p->payment_method->name : '---' }}</td>
                    <td style="text-align:center">{{ number_format($p->montant, 2) }}</td>
                    <td style="text-align:right">{{ number_format($p->change, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <div class="footer">
            <p>{{ $setting->CompanyName }}</p>
        </div>
    </div>
</body>
</html>
