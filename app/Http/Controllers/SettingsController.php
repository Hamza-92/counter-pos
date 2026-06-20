<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Language;
use App\Models\PaymentMethod;
use App\Models\PosSetting;
use App\Models\Setting;
use App\Models\sms_gateway;
use App\Models\User;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Intervention\Image\ImageManagerStatic as Image;

class SettingsController extends Controller
{
    // -------------- Update  Settings ---------------\\

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Setting::class);

        $setting = Setting::findOrFail($id);
        $currentAvatar = $setting->logo;
        // Only process logo if a file was actually uploaded
        if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            $image = $request->file('logo');
            $path = public_path().'/images';
            $filename = rand(11111111, 99999999).$image->getClientOriginalName();

            $image_resize = Image::make($image->getRealPath());

             // Resize to one standard size (800x800)
             $image_resize->resize(800, 800, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })->save(public_path('/images/'.$filename));

            $userPhoto = $path.'/'.$currentAvatar;
            if (file_exists($userPhoto)) {
                if ($setting->logo != 'logo-default.png') {
                    @unlink($userPhoto);
                }
            }
        } else {
            $filename = $currentAvatar;
        }
        if ($request['currency'] != 'null') {
            $currency = $request['currency'];
        } else {
            $currency = null;
        }

        if ($request['client'] != 'null') {
            $client = $request['client'];
        } else {
            $client = null;
        }

        if ($request['warehouse'] != 'null') {
            $warehouse = $request['warehouse'];
        } else {
            $warehouse = null;
        }

        if ($request['sms_gateway'] != 'null') {
            $sms_gateway = $request['sms_gateway'];
        } else {
            $sms_gateway = null;
        }

        $default_account_id = null;
        if (isset($request['default_account']) && $request['default_account'] !== '' && $request['default_account'] !== 'null') {
            $default_account_id = (int) $request['default_account'];
        }
        $default_payment_method_id = null;
        if (isset($request['default_payment_method']) && $request['default_payment_method'] !== '' && $request['default_payment_method'] !== 'null') {
            $default_payment_method_id = (int) $request['default_payment_method'];
        }

        if ($request['default_language'] != 'null') {
            $default_language = $request['default_language'];
        } else {
            $default_language = 'en';
        }

        if ($request['is_invoice_footer'] == '1' || $request['is_invoice_footer'] == 'true') {
            $is_invoice_footer = 1;
        } else {
            $is_invoice_footer = 0;
        }

        if ($request['quotation_with_stock'] == '1' || $request['quotation_with_stock'] == 'true') {
            $quotation_with_stock = 1;
        } else {
            $quotation_with_stock = 0;
        }

        if ($request['show_language'] == '1' || $request['show_language'] == 'true') {
            $show_language = 1;
        } else {
            $show_language = 0;
        }

        // Dark Mode and RTL settings
        $dark_mode = ($request['dark_mode'] == '1' || $request['dark_mode'] == 'true' || $request['dark_mode'] === 1 || $request['dark_mode'] === true) ? 1 : 0;
        $rtl = ($request['rtl'] == '1' || $request['rtl'] == 'true' || $request['rtl'] === 1 || $request['rtl'] === true) ? 1 : 0;

        // Normalize invoice_format: only allow 'thermal' or 'a4', default to 'thermal'
        $invoice_format = 'thermal';
        if (isset($request['invoice_format']) && in_array($request['invoice_format'], ['thermal', 'a4'], true)) {
            $invoice_format = $request['invoice_format'];
        } elseif (!empty($setting->invoice_format) && in_array($setting->invoice_format, ['thermal', 'a4'], true)) {
            $invoice_format = $setting->invoice_format;
        }

        // Normalize A4 invoice logo dimensions: clamp to a sensible pixel range.
        // Defaults match the historical CSS values used in the A4 invoice header.
        $invoice_logo_width = (int) ($request['invoice_logo_width'] ?? $setting->invoice_logo_width ?? 180);
        if ($invoice_logo_width < 20) {
            $invoice_logo_width = 20;
        } elseif ($invoice_logo_width > 600) {
            $invoice_logo_width = 600;
        }

        $invoice_logo_height = (int) ($request['invoice_logo_height'] ?? $setting->invoice_logo_height ?? 60);
        if ($invoice_logo_height < 20) {
            $invoice_logo_height = 20;
        } elseif ($invoice_logo_height > 400) {
            $invoice_logo_height = 400;
        }

        Setting::whereId($id)->update([
            'currency_id' => $currency,
            'client_id' => $client,
            'warehouse_id' => $warehouse,
            'default_account_id' => $default_account_id,
            'default_payment_method_id' => $default_payment_method_id,
            'email' => $request['email'],
            'default_language' => $default_language,
            'CompanyName' => $request['CompanyName'],
            'CompanyPhone' => $request['CompanyPhone'],
            'CompanyAdress' => $request['CompanyAdress'],
            'company_name_ar' => $request['company_name_ar'] ?? $setting->company_name_ar,
            'vat_number' => $request['vat_number'] ?? $setting->vat_number,
            'zatca_enabled' => ($request['zatca_enabled'] == '1' || $request['zatca_enabled'] == 'true' || $request['zatca_enabled'] === 1 || $request['zatca_enabled'] === true) ? 1 : 0,
            'footer' => $request['footer'],
            'developed_by' => $request['developed_by'],
            'is_invoice_footer' => $is_invoice_footer,
            'invoice_format' => $invoice_format,
            'invoice_logo_width' => $invoice_logo_width,
            'invoice_logo_height' => $invoice_logo_height,
            'quotation_with_stock' => $quotation_with_stock,
            'show_language' => $show_language,
            'dark_mode' => $dark_mode,
            'rtl' => $rtl,
            'invoice_footer' => $request['invoice_footer'],
            'sms_gateway' => $sms_gateway,
            'logo' => $filename,
            'point_to_amount_rate' => $request['point_to_amount_rate'],
            'default_tax' => $request['default_tax'] ?? 0,
            'default_dashboard_date_range' => in_array($request['default_dashboard_date_range'] ?? 'week', ['today', 'week', 'month'], true) ? $request['default_dashboard_date_range'] : 'week',
            'dashboard_section_order' => $request['dashboard_section_order'] ?? null,
            'dashboard_grid_layout' => $request['dashboard_grid_layout'] ?? null,
            'dashboard_font_size' => $request['dashboard_font_size'] ?? null,
            'dashboard_font_family' => $request['dashboard_font_family'] ?? null,
            'date_format' => $request['date_format'] ?? 'YYYY-MM-DD',
            'sale_prefix' => $request['sale_prefix'] ?? null,
            'purchase_prefix' => $request['purchase_prefix'] ?? null,
            'quotation_prefix' => $request['quotation_prefix'] ?? null,
            'adjustment_prefix' => $request['adjustment_prefix'] ?? null,
            'transfer_prefix' => $request['transfer_prefix'] ?? null,
            'sale_return_prefix' => $request['sale_return_prefix'] ?? null,
            'purchase_return_prefix' => $request['purchase_return_prefix'] ?? null,
            // Optional price format for frontend display (POS, etc.)
            'price_format' => $request['price_format'] ?? $setting->price_format,
            // Cloud backup settings
            'backup_cloud_enabled' => ($request['backup_cloud_enabled'] == '1' || $request['backup_cloud_enabled'] == 'true' || $request['backup_cloud_enabled'] === 1 || $request['backup_cloud_enabled'] === true) ? 1 : 0,
            'backup_keep_local' => ($request['backup_keep_local'] == '1' || $request['backup_keep_local'] == 'true' || $request['backup_keep_local'] === 1 || $request['backup_keep_local'] === true) ? 1 : 0,
            'backup_cloud_provider' => $request['backup_cloud_provider'] ?? null,
            'backup_cloud_path' => $request['backup_cloud_path'] ?? null,
            // S3-compatible settings
            'backup_s3_bucket' => $request['backup_s3_bucket'] ?? null,
            'backup_s3_region' => $request['backup_s3_region'] ?? null,
            'backup_s3_access_key' => $request['backup_s3_access_key'] ?? null,
            'backup_s3_secret_key' => $request->has('backup_s3_secret_key') && $request['backup_s3_secret_key'] !== '' ? $request['backup_s3_secret_key'] : $setting->backup_s3_secret_key,
            'backup_s3_endpoint' => $request['backup_s3_endpoint'] ?? null,
            'backup_s3_path_style' => ($request['backup_s3_path_style'] == '1' || $request['backup_s3_path_style'] == 'true' || $request['backup_s3_path_style'] === 1 || $request['backup_s3_path_style'] === true) ? 1 : 0,
            // Google Drive settings
            'backup_gdrive_folder_id' => $request['backup_gdrive_folder_id'] ?? null,
            'backup_gdrive_access_token' => $request->has('backup_gdrive_access_token') && $request['backup_gdrive_access_token'] !== '' ? $request['backup_gdrive_access_token'] : $setting->backup_gdrive_access_token,
            'backup_gdrive_refresh_token' => $request->has('backup_gdrive_refresh_token') && $request['backup_gdrive_refresh_token'] !== '' ? $request['backup_gdrive_refresh_token'] : $setting->backup_gdrive_refresh_token,
            'backup_gdrive_client_id' => $request['backup_gdrive_client_id'] ?? null,
            'backup_gdrive_client_secret' => $request->has('backup_gdrive_client_secret') && $request['backup_gdrive_client_secret'] !== '' ? $request['backup_gdrive_client_secret'] : $setting->backup_gdrive_client_secret,
            // Dropbox settings
            'backup_dropbox_path' => $request['backup_dropbox_path'] ?? null,
            'backup_dropbox_access_token' => $request->has('backup_dropbox_access_token') && $request['backup_dropbox_access_token'] !== '' ? $request['backup_dropbox_access_token'] : $setting->backup_dropbox_access_token,
        ] + $this->pharmacySettingsPayload($request, $setting));

        if (! empty($currency)) {
            $currencyModel = \App\Models\Currency::find($currency);
            if ($currencyModel) {
                \App\Models\StoreSetting::query()->update([
                    'currency_code' => $currencyModel->symbol,
                ]);
            }
        }

        // Set selected language as default (only if language is provided and exists)
        if (! empty($default_language)) {
            $language = Language::where('locale', $default_language)->first();

            // Only process if language was found
            if ($language) {
                // Skip if already default
                if (! $language->is_default) {
                    // Set this one as default
                    $language->update(['is_default' => true]);

                    // Unset others
                    Language::where('id', '!=', $language->id)
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }
            }
        }

        // Prepare environment values
        $envValues = [
            'APP_TIMEZONE' => $request['timezone'] !== null ? '"'.$request['timezone'].'"' : '"UTC"',
        ];

        // Handle Debug Mode
        if ($request->has('debug_mode')) {
            $debug_mode = ($request['debug_mode'] == '1' || $request['debug_mode'] == 'true' || $request['debug_mode'] === 1 || $request['debug_mode'] === true) ? 'true' : 'false';
            $envValues['APP_DEBUG'] = $debug_mode;
        }

        $this->setEnvironmentValue($envValues);

        // Clear config cache first to remove old cached values
        Artisan::call('config:clear');
        
        // If APP_DEBUG was changed, also clear route and view cache for immediate effect
        if ($request->has('debug_mode')) {
            Artisan::call('route:clear');
            Artisan::call('view:clear');
        }
        
        // Re-cache config with new values
        Artisan::call('config:cache');

        return response()->json(['success' => true]);
    }

    // -------------- Dark Mode: Get current status ---------------\\

    /**
     * Return the current dark_mode flag from settings.
     * This endpoint is intentionally minimal and independent of other settings APIs.
     */
    public function getDarkMode(Request $request)
    {

        $settings = Setting::where('deleted_at', '=', null)->first();

        return response()->json([
            'dark_mode' => $settings ? (bool) ($settings->dark_mode ?? false) : false,
        ], 200);
    }

    // -------------- Dark Mode: Update status ---------------\\

    /**
     * Update the dark_mode flag only.
     * This endpoint is dedicated to Dark Mode and does not touch any other settings.
     */
    public function updateDarkMode(Request $request)
    {

        $request->validate([
            'dark_mode' => 'required',
        ]);

        $settings = Setting::where('deleted_at', '=', null)->first();
        if (! $settings) {
            return response()->json(['message' => 'Settings not found'], 404);
        }

        $dark_mode = (
            $request['dark_mode'] == '1' ||
            $request['dark_mode'] == 'true' ||
            $request['dark_mode'] === 1 ||
            $request['dark_mode'] === true
        ) ? 1 : 0;

        $settings->dark_mode = $dark_mode;
        $settings->save();

        return response()->json([
            'success'   => true,
            'dark_mode' => (bool) $settings->dark_mode,
        ], 200);
    }

    // -------------- Get Pos Settings ---------------\\

    public function get_pos_Settings(Request $request)
    {

        $PosSetting = PosSetting::where('deleted_at', '=', null)->first();

        return response()->json([
            'pos_settings' => $PosSetting,
        ], 200);

    }

    public function get_pos_Settings_api(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'pos_settings', Setting::class);

        $PosSetting = PosSetting::where('deleted_at', '=', null)->first();

        return response()->json([
            'pos_settings' => $PosSetting,
        ], 200);

    }

    // -------------- Update Pos settings ---------------\\

    public function update_pos_settings(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'pos_settings', Setting::class);

        // Partial updates are supported: each field is applied only when
        // present (see $request->has() checks below). Don't hard-require
        // note_customer so callers that update a single field (e.g. the
        // Defaults tab saving only products_per_page) aren't rejected.
        $request->validate([
            'note_customer' => 'sometimes|nullable|string',
        ]);

        $posSettings = PosSetting::findOrFail($id);

        // Allow POS Settings endpoint to also update the global invoice_format
        // so that users can control the invoice layout (thermal vs A4) directly
        // from the POS Settings screens.
        if ($request->has('invoice_format')) {
            $settings = Setting::where('deleted_at', '=', null)->first();
            if ($settings) {
                // Normalize invoice_format: only allow 'thermal' or 'a4', default to existing or 'thermal'
                $invoice_format = 'thermal';
                if (isset($request['invoice_format']) && in_array($request['invoice_format'], ['thermal', 'a4'], true)) {
                    $invoice_format = $request['invoice_format'];
                } elseif (!empty($settings->invoice_format) && in_array($settings->invoice_format, ['thermal', 'a4'], true)) {
                    $invoice_format = $settings->invoice_format;
                }

                $settings->invoice_format = $invoice_format;
                $settings->save();
            }
        }

        // Build a partial update array so that each page only updates
        // the fields it actually sends. This prevents POS Receipt submit
        // from resetting POS Settings toggles (and vice versa).
        $data = [];

        // Common text field
        if ($request->has('note_customer')) {
            $data['note_customer'] = $request->input('note_customer');
        }

        // Receipt-related toggles
        if ($request->has('show_logo')) {
            $data['show_logo'] = ($request['show_logo'] == '1' || $request['show_logo'] == 'true' || $request['show_logo'] === true) ? 1 : 0;
        }

        if ($request->has('logo_size')) {
            // Validate logo_size: must be between 20 and 200 pixels
            $logoSize = (int) $request['logo_size'];
            $data['logo_size'] = ($logoSize >= 20 && $logoSize <= 200) ? $logoSize : 60;
        }

        if ($request->has('show_store_name')) {
            $data['show_store_name'] = ($request['show_store_name'] == '1' || $request['show_store_name'] == 'true' || $request['show_store_name'] === true) ? 1 : 0;
        }

        if ($request->has('show_reference')) {
            $data['show_reference'] = ($request['show_reference'] == '1' || $request['show_reference'] == 'true' || $request['show_reference'] === true) ? 1 : 0;
        }

        if ($request->has('show_date')) {
            $data['show_date'] = ($request['show_date'] == '1' || $request['show_date'] == 'true' || $request['show_date'] === true) ? 1 : 0;
        }

        if ($request->has('show_seller')) {
            $data['show_seller'] = ($request['show_seller'] == '1' || $request['show_seller'] == 'true' || $request['show_seller'] === true) ? 1 : 0;
        }

        if ($request->has('show_note')) {
            $data['show_note'] = $request['show_note'];
        }

        if ($request->has('show_barcode')) {
            $data['show_barcode'] = $request['show_barcode'];
        }

        if ($request->has('show_discount')) {
            $data['show_discount'] = $request['show_discount'];
        }

        if ($request->has('show_tax')) {
            $data['show_tax'] = ($request['show_tax'] == '1' || $request['show_tax'] == 'true' || $request['show_tax'] === true) ? 1 : 0;
        }

        if ($request->has('show_shipping')) {
            $data['show_shipping'] = ($request['show_shipping'] == '1' || $request['show_shipping'] == 'true' || $request['show_shipping'] === true) ? 1 : 0;
        }

        if ($request->has('show_customer')) {
            $data['show_customer'] = $request['show_customer'];
        }

        if ($request->has('show_email')) {
            $data['show_email'] = $request['show_email'];
        }

        if ($request->has('show_phone')) {
            $data['show_phone'] = $request['show_phone'];
        }

        if ($request->has('show_address')) {
            $data['show_address'] = $request['show_address'];
        }

        if ($request->has('receipt_paper_size')) {
            // Sanitize receipt paper size (58mm, 80mm, 88mm)
            $candidateSize = (int) $request['receipt_paper_size'];
            $allowedSizes = [58, 80, 88];
            $data['receipt_paper_size'] = in_array($candidateSize, $allowedSizes, true) ? $candidateSize : 80;
        }

        if ($request->has('show_paid')) {
            $data['show_paid'] = ($request['show_paid'] == '1' || $request['show_paid'] == 'true' || $request['show_paid'] === true) ? 1 : 0;
        }

        if ($request->has('show_due')) {
            $data['show_due'] = ($request['show_due'] == '1' || $request['show_due'] == 'true' || $request['show_due'] === true) ? 1 : 0;
        }

        if ($request->has('show_payments')) {
            $data['show_payments'] = ($request['show_payments'] == '1' || $request['show_payments'] == 'true' || $request['show_payments'] === true) ? 1 : 0;
        }

        if ($request->has('show_zatca_qr')) {
            $data['show_zatca_qr'] = ($request['show_zatca_qr'] == '1' || $request['show_zatca_qr'] == 'true' || $request['show_zatca_qr'] === true) ? 1 : 0;
        }

        if ($request->has('cash_drawer_auto_open')) {
            $data['cash_drawer_auto_open'] = ($request['cash_drawer_auto_open'] == '1' || $request['cash_drawer_auto_open'] == 'true' || $request['cash_drawer_auto_open'] === true);
        }

        if ($request->has('cash_drawer_printer_name')) {
            $data['cash_drawer_printer_name'] = $request->input('cash_drawer_printer_name') ?: null;
        }

        // Direct Network Printing: purely additive settings used by future
        // raw-socket (RAW/9100) printing. Existing driver-based printing paths
        // continue to work unchanged when this toggle is OFF.
        if ($request->has('direct_network_printing')) {
            $data['direct_network_printing'] = ($request['direct_network_printing'] == '1' || $request['direct_network_printing'] == 'true' || $request['direct_network_printing'] === true) ? 1 : 0;
        }

        if ($request->has('network_printer_ip')) {
            $ip = trim((string) $request->input('network_printer_ip'));
            $data['network_printer_ip'] = $ip === '' ? null : $ip;
        }

        if ($request->has('network_printer_port')) {
            $port = (int) $request->input('network_printer_port');
            $data['network_printer_port'] = ($port >= 1 && $port <= 65535) ? $port : null;
        }

        if ($request->has('is_printable')) {
            $data['is_printable'] = ($request['is_printable'] == '1' || $request['is_printable'] == 'true' || $request['is_printable'] === true) ? 1 : 0;
        }

        if ($request->has('show_Warehouse')) {
            $data['show_Warehouse'] = ($request['show_Warehouse'] == '1' || $request['show_Warehouse'] == 'true' || $request['show_Warehouse'] === true) ? 1 : 0;
        }

        // POS behaviour / display toggles (POS Settings page)
        if ($request->has('quick_add_customer')) {
            $data['quick_add_customer'] = ($request['quick_add_customer'] == '1' || $request['quick_add_customer'] == 'true' || $request['quick_add_customer'] === true) ? 1 : 0;
        }

        if ($request->has('barcode_scanning_sound')) {
            $data['barcode_scanning_sound'] = ($request['barcode_scanning_sound'] == '1' || $request['barcode_scanning_sound'] == 'true' || $request['barcode_scanning_sound'] === true) ? 1 : 0;
        }

        if ($request->has('show_product_images')) {
            $data['show_product_images'] = ($request['show_product_images'] == '1' || $request['show_product_images'] == 'true' || $request['show_product_images'] === true) ? 1 : 0;
        }

        if ($request->has('show_stock_quantity')) {
            $data['show_stock_quantity'] = ($request['show_stock_quantity'] == '1' || $request['show_stock_quantity'] == 'true' || $request['show_stock_quantity'] === true) ? 1 : 0;
        }

        if ($request->has('enable_hold_sales')) {
            $data['enable_hold_sales'] = ($request['enable_hold_sales'] == '1' || $request['enable_hold_sales'] == 'true' || $request['enable_hold_sales'] === true) ? 1 : 0;
        }

        if ($request->has('enable_customer_points')) {
            $data['enable_customer_points'] = ($request['enable_customer_points'] == '1' || $request['enable_customer_points'] == 'true' || $request['enable_customer_points'] === true) ? 1 : 0;
        }

        if ($request->has('show_categories')) {
            $data['show_categories'] = ($request['show_categories'] == '1' || $request['show_categories'] == 'true' || $request['show_categories'] === true) ? 1 : 0;
        }

        if ($request->has('show_brands')) {
            $data['show_brands'] = ($request['show_brands'] == '1' || $request['show_brands'] == 'true' || $request['show_brands'] === true) ? 1 : 0;
        }

        // Allow Overselling: when ON, POS bypasses stock checks and lets stock go negative.
        // Default OFF preserves existing strict stock-check behavior for upgraded installs.
        if ($request->has('allow_overselling')) {
            $data['allow_overselling'] = ($request['allow_overselling'] == '1' || $request['allow_overselling'] == 'true' || $request['allow_overselling'] === true) ? 1 : 0;
        }

        if ($request->has('products_per_page')) {
            $data['products_per_page'] = $request['products_per_page'];
        }

        // Receipt layout (1, 2, 3, 4) – only if sent (POS Receipt page)
        if ($request->has('receipt_layout')) {
            $candidate = (int) $request['receipt_layout'];
            $data['receipt_layout'] = in_array($candidate, [1, 2, 3, 4], true) ? $candidate : 1;
        }

        if (! empty($data)) {
            $posSettings->update($data);
        }

        return response()->json(['success' => true]);
    } 

    // -------------- Get All Settings ---------------\\

    public function get_Settings_data_api(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Setting::class);

        $settings = Setting::where('deleted_at', '=', null)->first();
        if ($settings) {
            if ($settings->currency_id) {
                if (Currency::where('id', $settings->currency_id)->where('deleted_at', '=', null)->first()) {
                    $item['currency_id'] = $settings->currency_id;
                } else {
                    $item['currency_id'] = '';
                }
            } else {
                $item['currency_id'] = '';
            }

            if ($settings->client_id) {
                if (Client::where('id', $settings->client_id)->where('deleted_at', '=', null)->first()) {
                    $item['client_id'] = $settings->client_id;
                } else {
                    $item['client_id'] = '';
                }
            } else {
                $item['client_id'] = '';
            }

            if ($settings->warehouse_id) {
                if (Warehouse::where('id', $settings->warehouse_id)->where('deleted_at', '=', null)->first()) {
                    $item['warehouse_id'] = $settings->warehouse_id;
                } else {
                    $item['warehouse_id'] = '';
                }
            } else {
                $item['warehouse_id'] = '';
            }

            if ($settings->sms_gateway) {
                if (sms_gateway::where('id', $settings->sms_gateway)->where('deleted_at', '=', null)->first()) {
                    $item['sms_gateway'] = $settings->sms_gateway;
                } else {
                    $item['sms_gateway'] = '';
                }
            } else {
                $item['sms_gateway'] = '';
            }

            $item['default_account_id'] = '';
            if (! empty($settings->default_account_id) && Account::where('id', $settings->default_account_id)->whereNull('deleted_at')->first()) {
                $item['default_account_id'] = $settings->default_account_id;
            }
            $item['default_payment_method_id'] = '';
            if (! empty($settings->default_payment_method_id) && PaymentMethod::where('id', $settings->default_payment_method_id)->whereNull('deleted_at')->first()) {
                $item['default_payment_method_id'] = $settings->default_payment_method_id;
            }

            $item['id'] = $settings->id;
            $item['email'] = $settings->email;
            $item['CompanyName'] = $settings->CompanyName;
            $item['CompanyPhone'] = $settings->CompanyPhone;
            $item['CompanyAdress'] = $settings->CompanyAdress;
            $item['logo'] = $settings->logo;
            $item['footer'] = $settings->footer;
            $item['developed_by'] = $settings->developed_by;
            $item['default_language'] = $settings->default_language;
            $item['is_invoice_footer'] = $settings->is_invoice_footer;
            $item['invoice_footer'] = $settings->invoice_footer;
            // Invoice format for POS printing: 'thermal' (default) or 'a4'
            $item['invoice_format'] = in_array($settings->invoice_format, ['thermal', 'a4'], true)
                ? $settings->invoice_format
                : 'thermal';
            // A4 invoice logo dimensions (pixels). Defaults match historical CSS.
            $item['invoice_logo_width'] = (int) ($settings->invoice_logo_width ?: 180);
            $item['invoice_logo_height'] = (int) ($settings->invoice_logo_height ?: 60);
            $item['quotation_with_stock'] = $settings->quotation_with_stock;
            $item['show_language'] = $settings->show_language;
            $item['dark_mode'] = (bool) ($settings->dark_mode ?? false);
            $item['rtl'] = (bool) ($settings->rtl ?? false);
            $item['point_to_amount_rate'] = $settings->point_to_amount_rate;
            $item['default_tax'] = $settings->default_tax;
            $item['default_dashboard_date_range'] = in_array($settings->default_dashboard_date_range ?? 'week', ['today', 'week', 'month'], true) ? $settings->default_dashboard_date_range : 'week';
            $item['dashboard_section_order'] = $settings->dashboard_section_order ?? null;
            $item['dashboard_font_size'] = $settings->dashboard_font_size ?? '';
            $item['dashboard_font_family'] = $settings->dashboard_font_family ?? '';
            $item['dashboard_grid_layout'] = $settings->dashboard_grid_layout ?? null;
            $item['sale_prefix'] = $settings->sale_prefix ?? '';
            $item['purchase_prefix'] = $settings->purchase_prefix ?? '';
            $item['quotation_prefix'] = $settings->quotation_prefix ?? '';
            $item['adjustment_prefix'] = $settings->adjustment_prefix ?? '';
            $item['transfer_prefix'] = $settings->transfer_prefix ?? '';
            $item['sale_return_prefix'] = $settings->sale_return_prefix ?? '';
            $item['purchase_return_prefix'] = $settings->purchase_return_prefix ?? '';
            // ZATCA settings
            $item['company_name_ar'] = $settings->company_name_ar;
            $item['vat_number'] = $settings->vat_number;
            $item['zatca_enabled'] = (bool) $settings->zatca_enabled;
            // Timezone from .env file - read directly from file to avoid cache issues
            $item['timezone'] = $this->getEnvValue('APP_TIMEZONE', 'UTC');
            // Debug Mode from .env file - read directly from file to avoid cache issues
            $item['debug_mode'] = $this->getEnvValue('APP_DEBUG', 'false') === 'true';
            $item['date_format'] = $settings->date_format ?? 'YYYY-MM-DD';
            // Optional price format for frontend display (used by POS)
            $item['price_format'] = $settings->price_format;
            // Cloud backup settings
            $item['backup_cloud_enabled'] = (bool) ($settings->backup_cloud_enabled ?? false);
            $item['backup_keep_local'] = (bool) ($settings->backup_keep_local ?? true);
            $item['backup_cloud_provider'] = $settings->backup_cloud_provider ?? null;
            $item['backup_cloud_path'] = $settings->backup_cloud_path ?? null;
            // S3-compatible settings
            $item['backup_s3_bucket'] = $settings->backup_s3_bucket ?? null;
            $item['backup_s3_region'] = $settings->backup_s3_region ?? null;
            $item['backup_s3_access_key'] = $settings->backup_s3_access_key ?? null;
            // Only include secret key if include_secrets is true (for security)
            $includeSecrets = $request->has('include_secrets') && ($request->input('include_secrets') == '1' || $request->input('include_secrets') == 'true' || $request->input('include_secrets') === 1 || $request->input('include_secrets') === true);
            if ($includeSecrets) {
                $item['backup_s3_secret_key'] = $settings->backup_s3_secret_key ?? null;
            } else {
                $item['backup_s3_has_secret_key'] = !empty($settings->backup_s3_secret_key);
            }
            $item['backup_s3_endpoint'] = $settings->backup_s3_endpoint ?? null;
            $item['backup_s3_path_style'] = (bool) ($settings->backup_s3_path_style ?? false);
            // Google Drive settings
            $item['backup_gdrive_folder_id'] = $settings->backup_gdrive_folder_id ?? null;
            if ($includeSecrets) {
                $item['backup_gdrive_access_token'] = $settings->backup_gdrive_access_token ?? null;
                $item['backup_gdrive_refresh_token'] = $settings->backup_gdrive_refresh_token ?? null;
                $item['backup_gdrive_client_secret'] = $settings->backup_gdrive_client_secret ?? null;
            } else {
                $item['backup_gdrive_has_access_token'] = !empty($settings->backup_gdrive_access_token);
                $item['backup_gdrive_has_refresh_token'] = !empty($settings->backup_gdrive_refresh_token);
                $item['backup_gdrive_has_client_secret'] = !empty($settings->backup_gdrive_client_secret);
            }
            $item['backup_gdrive_client_id'] = $settings->backup_gdrive_client_id ?? null;
            // Dropbox settings
            $item['backup_dropbox_path'] = $settings->backup_dropbox_path ?? null;
            if ($includeSecrets) {
                $item['backup_dropbox_access_token'] = $settings->backup_dropbox_access_token ?? null;
            } else {
                $item['backup_dropbox_has_access_token'] = !empty($settings->backup_dropbox_access_token);
            }

            // Pharmacy mode (batch & expiry tracking) — opt-in, only present once migration has run
            $item['pharmacy_mode_supported'] = \Schema::hasColumn('settings', 'pharmacy_mode');
            $item['pharmacy_mode'] = (bool) ($settings->pharmacy_mode ?? false);
            $item['expiry_warning_days'] = (int) ($settings->expiry_warning_days ?? 90);
            $item['block_expired_sale'] = (bool) ($settings->block_expired_sale ?? false);
            $item['print_expiry_on_receipt'] = (bool) ($settings->print_expiry_on_receipt ?? false);

            $zones_array = [];
            $timestamp = time();
            foreach (timezone_identifiers_list() as $key => $zone) {
                date_default_timezone_set($zone);
                $zones_array[$key]['zone'] = $zone;
                $zones_array[$key]['diff_from_GMT'] = 'UTC/GMT '.date('P', $timestamp);
                $zones_array[$key]['label'] = $zones_array[$key]['diff_from_GMT'].' - '.$zones_array[$key]['zone'];
            }

            $Currencies = Currency::where('deleted_at', null)->get(['id', 'name']);
            $clients = client::where('deleted_at', '=', null)->get(['id', 'name']);
            $sms_gateway = sms_gateway::where('deleted_at', '=', null)->get(['id', 'title']);

            // get warehouses assigned to user
            $user_auth = auth()->user();
            if ($user_auth->is_all_warehouses) {
                $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
            } else {
                $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
                $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
            }

            $languages = Language::where('is_active', true)->get(['name', 'locale']);
            $accounts = Account::whereNull('deleted_at')->get(['id', 'account_name', 'account_num']);
            $payment_methods = PaymentMethod::whereNull('deleted_at')->get(['id', 'name']);

            return response()->json([
                'settings' => $item,
                'currencies' => $Currencies,
                'clients' => $clients,
                'warehouses' => $warehouses,
                'sms_gateway' => $sms_gateway,
                'accounts' => $accounts,
                'payment_methods' => $payment_methods,
                'zones_array' => $zones_array,
                'languages' => $languages,
            ], 200);
        } else {
            return response()->json(['statut' => 'error'], 500);
        }
    }

    /**
     * Update only the dashboard grid layout (drag/resize positions).
     */
    public function updateDashboardGridLayout(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'update', Setting::class);
        $setting = Setting::whereNull('deleted_at')->first();
        if (!$setting) {
            return response()->json(['message' => 'Settings not found'], 404);
        }
        $layout = $request->input('dashboard_grid_layout');
        if ($layout !== null && !is_string($layout)) {
            $layout = json_encode($layout);
        }
        $setting->dashboard_grid_layout = $layout;
        $setting->save();
        return response()->json(['success' => true, 'dashboard_grid_layout' => $setting->dashboard_grid_layout]);
    }

    public function getSettings(Request $request)
    {

        $settings = Setting::where('deleted_at', '=', null)->first();
        if ($settings) {
            if ($settings->currency_id) {
                if (Currency::where('id', $settings->currency_id)->where('deleted_at', '=', null)->first()) {
                    $item['currency_id'] = $settings->currency_id;
                } else {
                    $item['currency_id'] = '';
                }
            } else {
                $item['currency_id'] = '';
            }

            if ($settings->client_id) {
                if (Client::where('id', $settings->client_id)->where('deleted_at', '=', null)->first()) {
                    $item['client_id'] = $settings->client_id;
                } else {
                    $item['client_id'] = '';
                }
            } else {
                $item['client_id'] = '';
            }

            if ($settings->warehouse_id) {
                if (Warehouse::where('id', $settings->warehouse_id)->where('deleted_at', '=', null)->first()) {
                    $item['warehouse_id'] = $settings->warehouse_id;
                } else {
                    $item['warehouse_id'] = '';
                }
            } else {
                $item['warehouse_id'] = '';
            }

            if ($settings->sms_gateway) {
                if (sms_gateway::where('id', $settings->sms_gateway)->where('deleted_at', '=', null)->first()) {
                    $item['sms_gateway'] = $settings->sms_gateway;
                } else {
                    $item['sms_gateway'] = '';
                }
            } else {
                $item['sms_gateway'] = '';
            }

            $item['default_account_id'] = '';
            if (! empty($settings->default_account_id) && Account::where('id', $settings->default_account_id)->whereNull('deleted_at')->first()) {
                $item['default_account_id'] = $settings->default_account_id;
            }
            $item['default_payment_method_id'] = '';
            if (! empty($settings->default_payment_method_id) && PaymentMethod::where('id', $settings->default_payment_method_id)->whereNull('deleted_at')->first()) {
                $item['default_payment_method_id'] = $settings->default_payment_method_id;
            }

            $item['id'] = $settings->id;
            $item['email'] = $settings->email;
            $item['CompanyName'] = $settings->CompanyName;
            $item['CompanyPhone'] = $settings->CompanyPhone;
            $item['CompanyAdress'] = $settings->CompanyAdress;
            $item['logo'] = $settings->logo;
            $item['footer'] = $settings->footer;
            $item['developed_by'] = $settings->developed_by;
            $item['default_language'] = $settings->default_language;
            $item['is_invoice_footer'] = $settings->is_invoice_footer;
            $item['invoice_footer'] = $settings->invoice_footer;
            // Invoice format for POS printing: 'thermal' (default) or 'a4'
            $item['invoice_format'] = in_array($settings->invoice_format, ['thermal', 'a4'], true)
                ? $settings->invoice_format
                : 'thermal';
            // A4 invoice logo dimensions (pixels). Defaults match historical CSS.
            $item['invoice_logo_width'] = (int) ($settings->invoice_logo_width ?: 180);
            $item['invoice_logo_height'] = (int) ($settings->invoice_logo_height ?: 60);
            $item['quotation_with_stock'] = $settings->quotation_with_stock;
            $item['show_language'] = $settings->show_language;
            $item['dark_mode'] = (bool) ($settings->dark_mode ?? false);
            $item['rtl'] = (bool) ($settings->rtl ?? false);
            $item['point_to_amount_rate'] = $settings->point_to_amount_rate;
            $item['default_tax'] = $settings->default_tax;
            $item['default_dashboard_date_range'] = in_array($settings->default_dashboard_date_range ?? 'week', ['today', 'week', 'month'], true) ? $settings->default_dashboard_date_range : 'week';
            $item['dashboard_section_order'] = $settings->dashboard_section_order ?? null;
            $item['dashboard_font_size'] = $settings->dashboard_font_size ?? '';
            $item['dashboard_font_family'] = $settings->dashboard_font_family ?? '';
            $item['dashboard_grid_layout'] = $settings->dashboard_grid_layout ?? null;
            $item['sale_prefix'] = $settings->sale_prefix ?? '';
            $item['purchase_prefix'] = $settings->purchase_prefix ?? '';
            $item['quotation_prefix'] = $settings->quotation_prefix ?? '';
            $item['adjustment_prefix'] = $settings->adjustment_prefix ?? '';
            $item['transfer_prefix'] = $settings->transfer_prefix ?? '';
            $item['sale_return_prefix'] = $settings->sale_return_prefix ?? '';
            $item['purchase_return_prefix'] = $settings->purchase_return_prefix ?? '';
            // ZATCA settings
            $item['company_name_ar'] = $settings->company_name_ar;
            $item['vat_number'] = $settings->vat_number;
            $item['zatca_enabled'] = (bool) $settings->zatca_enabled;
            // Timezone from .env file - read directly from file to avoid cache issues
            $item['timezone'] = $this->getEnvValue('APP_TIMEZONE', 'UTC');
            // Debug Mode from .env file - read directly from file to avoid cache issues
            $item['debug_mode'] = $this->getEnvValue('APP_DEBUG', 'false') === 'true';
            $item['date_format'] = $settings->date_format ?? 'YYYY-MM-DD';
            // Optional price format for frontend display (used by POS)
            $item['price_format'] = $settings->price_format;
            // Cloud backup settings
            $item['backup_cloud_enabled'] = (bool) ($settings->backup_cloud_enabled ?? false);
            $item['backup_keep_local'] = (bool) ($settings->backup_keep_local ?? true);
            $item['backup_cloud_provider'] = $settings->backup_cloud_provider ?? null;
            $item['backup_cloud_path'] = $settings->backup_cloud_path ?? null;
            // S3-compatible settings
            $item['backup_s3_bucket'] = $settings->backup_s3_bucket ?? null;
            $item['backup_s3_region'] = $settings->backup_s3_region ?? null;
            $item['backup_s3_access_key'] = $settings->backup_s3_access_key ?? null;
            // Only include secret key if include_secrets is true (for security)
            $includeSecrets = $request->has('include_secrets') && ($request->input('include_secrets') == '1' || $request->input('include_secrets') == 'true' || $request->input('include_secrets') === 1 || $request->input('include_secrets') === true);
            if ($includeSecrets) {
                $item['backup_s3_secret_key'] = $settings->backup_s3_secret_key ?? null;
            } else {
                $item['backup_s3_has_secret_key'] = !empty($settings->backup_s3_secret_key);
            }
            $item['backup_s3_endpoint'] = $settings->backup_s3_endpoint ?? null;
            $item['backup_s3_path_style'] = (bool) ($settings->backup_s3_path_style ?? false);
            // Google Drive settings
            $item['backup_gdrive_folder_id'] = $settings->backup_gdrive_folder_id ?? null;
            if ($includeSecrets) {
                $item['backup_gdrive_access_token'] = $settings->backup_gdrive_access_token ?? null;
                $item['backup_gdrive_refresh_token'] = $settings->backup_gdrive_refresh_token ?? null;
                $item['backup_gdrive_client_secret'] = $settings->backup_gdrive_client_secret ?? null;
            } else {
                $item['backup_gdrive_has_access_token'] = !empty($settings->backup_gdrive_access_token);
                $item['backup_gdrive_has_refresh_token'] = !empty($settings->backup_gdrive_refresh_token);
                $item['backup_gdrive_has_client_secret'] = !empty($settings->backup_gdrive_client_secret);
            }
            $item['backup_gdrive_client_id'] = $settings->backup_gdrive_client_id ?? null;
            // Dropbox settings
            $item['backup_dropbox_path'] = $settings->backup_dropbox_path ?? null;
            if ($includeSecrets) {
                $item['backup_dropbox_access_token'] = $settings->backup_dropbox_access_token ?? null;
            } else {
                $item['backup_dropbox_has_access_token'] = !empty($settings->backup_dropbox_access_token);
            }

            // Pharmacy mode (batch & expiry tracking) — opt-in, only present once migration has run
            $item['pharmacy_mode_supported'] = \Schema::hasColumn('settings', 'pharmacy_mode');
            $item['pharmacy_mode'] = (bool) ($settings->pharmacy_mode ?? false);
            $item['expiry_warning_days'] = (int) ($settings->expiry_warning_days ?? 90);
            $item['block_expired_sale'] = (bool) ($settings->block_expired_sale ?? false);
            $item['print_expiry_on_receipt'] = (bool) ($settings->print_expiry_on_receipt ?? false);

            $zones_array = [];
            $timestamp = time();
            foreach (timezone_identifiers_list() as $key => $zone) {
                date_default_timezone_set($zone);
                $zones_array[$key]['zone'] = $zone;
                $zones_array[$key]['diff_from_GMT'] = 'UTC/GMT '.date('P', $timestamp);
                $zones_array[$key]['label'] = $zones_array[$key]['diff_from_GMT'].' - '.$zones_array[$key]['zone'];
            }

            $Currencies = Currency::where('deleted_at', null)->get(['id', 'name']);
            $clients = client::where('deleted_at', '=', null)->get(['id', 'name']);
            $sms_gateway = sms_gateway::where('deleted_at', '=', null)->get(['id', 'title']);

            // get warehouses assigned to user
            $user_auth = auth()->user();
            if ($user_auth->is_all_warehouses) {
                $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
            } else {
                $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
                $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
            }

            $languages = Language::where('is_active', true)->get(['name', 'locale']);

            return response()->json([
                'settings' => $item,
                'currencies' => $Currencies,
                'clients' => $clients,
                'warehouses' => $warehouses,
                'sms_gateway' => $sms_gateway,
                'zones_array' => $zones_array,
                'languages' => $languages,
            ], 200);
        } else {
            return response()->json(['statut' => 'error'], 500);
        }
    }

    // -------------- Clear_Cache ---------------\\

    public function Clear_Cache(Request $request)
    {
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');
    }

    // -------------- Get Environment Value Directly from .env File ---------------\\

    private function getEnvValue($key, $default = null)
    {
        $envFile = app()->environmentFilePath();
        if (!file_exists($envFile)) {
            return $default;
        }
        
        $content = file_get_contents($envFile);
        $lines = preg_split('/\r\n|\r|\n/', $content);
        
        foreach ($lines as $line) {
            // Skip comments and empty lines
            if (empty(trim($line)) || strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Check if line contains the key
            if (strpos($line, $key . '=') === 0) {
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $value = trim($parts[1]);
                    // Remove quotes if present
                    $value = trim($value, '"\'');
                    return $value;
                }
            }
        }
        
        return $default;
    }

    // -------------- Set Environment Value ---------------\\

    public function setEnvironmentValue(array $values)
    {
        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);
        $str .= "\r\n";
        if (count($values) > 0) {
            foreach ($values as $envKey => $envValue) {

                $keyPosition = strpos($str, "$envKey=");
                $endOfLinePosition = strpos($str, "\n", $keyPosition);
                $oldLine = substr($str, $keyPosition, $endOfLinePosition - $keyPosition);

                if (is_bool($keyPosition) && $keyPosition === false) {
                    // variable doesnot exist
                    $str .= "$envKey=$envValue";
                    $str .= "\r\n";
                } else {
                    // variable exist
                    $str = str_replace($oldLine, "$envKey=$envValue", $str);
                }
            }
        }

        $str = substr($str, 0, -1);
        if (! file_put_contents($envFile, $str)) {
            return false;
        }

        app()->loadEnvironmentFrom($envFile);

        return true;
    }

    public function get_appearance_settings(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'appearance_settings', Setting::class);

        $settings = Setting::where('deleted_at', '=', null)->first();
        if ($settings) {

            $item['id'] = $settings->id;

            $item['favicon'] = $settings->favicon;
            $item['app_name'] = $settings->app_name;
            $item['page_title_suffix'] = $settings->page_title_suffix;
            $item['customize_button_visible'] = (bool) $settings->customize_button_visible;
            $item['hide_site_name'] = (bool) ($settings->hide_site_name ?? false);
            $item['logo'] = $settings->logo;
            $item['footer'] = $settings->footer;
            $item['developed_by'] = $settings->developed_by;
            // Login page appearance
            $item['login_hero_title'] = $settings->login_hero_title;
            $item['login_hero_subtitle'] = $settings->login_hero_subtitle;
            $item['login_panel_title'] = $settings->login_panel_title;
            $item['login_panel_subtitle'] = $settings->login_panel_subtitle;
            $item['login_hero_badge'] = $settings->login_hero_badge;
            $item['login_hero_feature_1'] = $settings->login_hero_feature_1;
            $item['login_hero_feature_2'] = $settings->login_hero_feature_2;
            $item['login_hero_feature_3'] = $settings->login_hero_feature_3;
            $item['login_btn_text'] = $settings->login_btn_text;
            $item['login_footer_text'] = $settings->login_footer_text;

            return response()->json([
                'settings' => $item,

            ], 200);
        } else {
            return response()->json(['statut' => 'error'], 500);
        }
    }

    public function update_appearance_settings(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'appearance_settings', Setting::class);

        $setting = Setting::findOrFail($id);
        $currentLogo = $setting->logo;
        $currentFavicon = $setting->favicon;

        $logoFilename = $currentLogo;
        $faviconFilename = $currentFavicon;

        // Handle Logo Upload
        if ($request->hasFile('logo') && $request->file('logo') != $currentLogo) {
            $logo = $request->file('logo');
            $logoFilename = rand(11111111, 99999999).$logo->getClientOriginalName();
            $logoPath = public_path('/images/'.$logoFilename);

            $imageResize = Image::make($logo->getRealPath())->resize(80, 80);
            $imageResize->save($logoPath);

            if ($currentLogo && $currentLogo != 'logo-default.png') {
                $oldLogoPath = public_path('/images/'.$currentLogo);
                if (file_exists($oldLogoPath)) {
                    @unlink($oldLogoPath);
                }
            }
        }

        // Handle Favicon Upload
        if ($request->hasFile('favicon') && $request->file('favicon')->isValid()) {
            $favicon = $request->file('favicon');
            $extension = strtolower($favicon->getClientOriginalExtension());

            if (in_array($extension, ['ico', 'png'])) {
                $faviconFilename = uniqid().'.'.$extension;
                $favicon->move(public_path('images'), $faviconFilename);

                // Delete old favicon if it exists and is not default
                if ($currentFavicon && $currentFavicon !== 'favicon.ico') {
                    $oldFaviconPath = public_path('images/'.$currentFavicon);
                    if (file_exists($oldFaviconPath)) {
                        @unlink($oldFaviconPath);
                    }
                }
            }
        }

        // Update settings
        $setting->update([
            'footer' => $request->input('footer'),
            'developed_by' => $request->input('developed_by'),
            'app_name' => $request->input('app_name'),
            'page_title_suffix' => $request->input('page_title_suffix'),
            'customize_button_visible' => filter_var($request->input('customize_button_visible'), FILTER_VALIDATE_BOOLEAN),
            'hide_site_name' => filter_var($request->input('hide_site_name'), FILTER_VALIDATE_BOOLEAN),
            'logo' => $logoFilename,
            'favicon' => $faviconFilename,
            // Login page appearance
            'login_hero_title' => $request->input('login_hero_title'),
            'login_hero_subtitle' => $request->input('login_hero_subtitle'),
            'login_panel_title' => $request->input('login_panel_title'),
            'login_panel_subtitle' => $request->input('login_panel_subtitle'),
            'login_hero_badge' => $request->input('login_hero_badge'),
            'login_hero_feature_1' => $request->input('login_hero_feature_1'),
            'login_hero_feature_2' => $request->input('login_hero_feature_2'),
            'login_hero_feature_3' => $request->input('login_hero_feature_3'),
            'login_btn_text' => $request->input('login_btn_text'),
            'login_footer_text' => $request->input('login_footer_text'),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Build the pharmacy-mode column updates to merge into the main settings update.
     * Returns an empty array if the pharmacy migration hasn't been run, so the main
     * UPDATE silently skips columns that don't exist (preserves backward compatibility).
     */
    protected function pharmacySettingsPayload(Request $request, $setting): array
    {
        if (! \Schema::hasColumn('settings', 'pharmacy_mode')) {
            return [];
        }
        $bool = function ($v) {
            return ($v === '1' || $v === 'true' || $v === 1 || $v === true) ? 1 : 0;
        };
        $warningDays = (int) ($request->input('expiry_warning_days', $setting->expiry_warning_days ?? 90));
        if ($warningDays < 0) {
            $warningDays = 0;
        } elseif ($warningDays > 3650) {
            $warningDays = 3650;
        }

        return [
            'pharmacy_mode' => $request->has('pharmacy_mode') ? $bool($request->input('pharmacy_mode')) : (int) ($setting->pharmacy_mode ?? 0),
            'expiry_warning_days' => $warningDays,
            'block_expired_sale' => $request->has('block_expired_sale') ? $bool($request->input('block_expired_sale')) : (int) ($setting->block_expired_sale ?? 0),
            'print_expiry_on_receipt' => $request->has('print_expiry_on_receipt') ? $bool($request->input('print_expiry_on_receipt')) : (int) ($setting->print_expiry_on_receipt ?? 0),
        ];
    }
}
