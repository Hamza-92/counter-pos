<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Unit;
use App\utils\helpers;
use ArPHP\I18N\Arabic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PDF;

class PortalInvoicePdfController extends Controller
{
    /**
     * GET /api/portal/invoices/{id}/pdf - Download invoice PDF (client-scoped, secure).
     */
    public function download(Request $request, $id)
    {
        $portalClient = Auth::guard('portal')->user();
        $this->assertPortalActive($portalClient);

        $sale = Sale::whereNull('deleted_at')
            ->where('client_id', $portalClient->client_id)
            ->where('id', $id)
            ->with(['details.product.unitSale', 'client'])
            ->firstOrFail();

        $helpers = new helpers;
        $settings = Setting::whereNull('deleted_at')->first();
        $symbol = $helpers->Get_Currency_Code();

        $sale_data = $sale;
        $sale = [];
        $sale['client_name'] = $sale_data->client->name ?? '';
        $sale['client_phone'] = $sale_data->client->phone ?? '';
        $sale['client_adr'] = $sale_data->client->adresse ?? '';
        $sale['client_email'] = $sale_data->client->email ?? '';
        $sale['client_tax'] = $sale_data->client->tax_number ?? '';
        $sale['TaxNet'] = number_format($sale_data->TaxNet ?? 0, 2, '.', '');
        $sale['discount'] = number_format($sale_data->discount ?? 0, 2, '.', '');
        $sale['discount_Method'] = $sale_data->discount_Method ?? '2';
        $sale['discount_from_points'] = number_format($sale_data->discount_from_points ?? 0, 2, '.', '');
        $sale['shipping'] = number_format($sale_data->shipping ?? 0, 2, '.', '');
        $sale['statut'] = $sale_data->statut;
        $sale['Ref'] = $sale_data->Ref;
        $sale['date'] = $sale_data->date . ' ' . ($sale_data->time ?? '');
        $sale['GrandTotal'] = number_format($sale_data->GrandTotal, 2, '.', '');
        $sale['paid_amount'] = number_format($sale_data->paid_amount ?? 0, 2, '.', '');
        $sale['due'] = number_format((float) $sale_data->GrandTotal - (float) ($sale_data->paid_amount ?? 0), 2, '.', '');
        $sale['payment_status'] = $sale_data->payment_statut;

        $details = [];
        $detail_id = 0;

        foreach ($sale_data->details as $detail) {
            $unit = null;
            if ($detail->sale_unit_id !== null) {
                $unit = Unit::find($detail->sale_unit_id);
            } else {
                $product_unit_sale = Product::with('unitSale')->find($detail->product_id);
                if ($product_unit_sale && $product_unit_sale->unitSale) {
                    $unit = Unit::find($product_unit_sale->unitSale->id);
                }
            }

            if ($detail->product_variant_id) {
                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();
                $data['code'] = $productsVariants ? $productsVariants->code : '';
                $data['name'] = ($productsVariants ? '[' . $productsVariants->name . ']' : '') . (optional($detail->product)->name ?? '');
            } else {
                $data['code'] = optional($detail->product)->code ?? '';
                $data['name'] = optional($detail->product)->name ?? '';
            }

            $data['detail_id'] = ++$detail_id;
            $data['quantity'] = number_format($detail->quantity, 2, '.', '');
            $data['total'] = number_format($detail->total, 2, '.', '');
            $data['unitSale'] = $unit ? $unit->ShortName : '';
            $data['price'] = number_format($detail->price, 2, '.', '');

            if (($detail->discount_method ?? '2') == '2') {
                $data['DiscountNet'] = number_format($detail->discount ?? 0, 2, '.', '');
            } else {
                $data['DiscountNet'] = number_format(($detail->price * ($detail->discount ?? 0) / 100), 2, '.', '');
            }

            $tax_price = ($detail->TaxNet ?? 0) * (($detail->price - ($data['DiscountNet'] ?? 0)) / 100);
            $data['Unit_price'] = number_format($detail->price, 2, '.', '');
            $data['discount'] = number_format($detail->discount ?? 0, 2, '.', '');

            if (($detail->tax_method ?? '1') == '1') {
                $data['Net_price'] = $detail->price - ($data['DiscountNet'] ?? 0);
                $data['taxe'] = number_format($tax_price, 2, '.', '');
            } else {
                $data['Net_price'] = $detail->price - ($data['DiscountNet'] ?? 0) - $tax_price;
                $data['taxe'] = number_format($detail->price - $data['Net_price'] - ($data['DiscountNet'] ?? 0), 2, '.', '');
            }
            $data['is_imei'] = optional($detail->product)->is_imei ?? 0;
            $data['imei_number'] = $detail->imei_number ?? '';

            $details[] = $data;
        }

        $Html = view('pdf.sale_pdf', [
            'symbol' => $symbol,
            'setting' => $settings,
            'sale' => $sale,
            'details' => $details,
        ])->render();

        $arabic = new Arabic;
        $p = $arabic->arIdentify($Html);
        for ($i = count($p) - 1; $i >= 0; $i -= 2) {
            $utf8ar = $arabic->utf8Glyphs(substr($Html, $p[$i - 1], $p[$i] - $p[$i - 1]));
            $Html = substr_replace($Html, $utf8ar, $p[$i - 1], $p[$i] - $p[$i - 1]);
        }

        $pdf = PDF::loadHTML($Html, 'UTF-8');

        return $pdf->download('invoice-' . $sale_data->Ref . '.pdf');
    }

    private function assertPortalActive($portalClient): void
    {
        if ((int) $portalClient->status !== 1) {
            abort(403, 'Portal access is disabled');
        }
    }
}
