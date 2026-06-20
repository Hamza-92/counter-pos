<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'currency_id', 'email', 'CompanyName', 'CompanyPhone', 'CompanyAdress', 'quotation_with_stock',
        'logo', 'footer', 'developed_by', 'client_id', 'warehouse_id', 'default_account_id', 'default_payment_method_id', 'default_language', 'show_language',
        'is_invoice_footer', 'invoice_footer', 'invoice_format', 'invoice_logo_width', 'invoice_logo_height', 'app_name', 'favicon', 'page_title_suffix', 'customize_button_visible', 'hide_site_name', 'point_to_amount_rate',
        'vat_number', 'company_name_ar', 'zatca_enabled', 'default_tax', 'default_dashboard_date_range', 'dashboard_section_order', 'dashboard_grid_layout', 'dashboard_font_size', 'dashboard_font_family', 'date_format',
        'sale_return_prefix', 'purchase_return_prefix',
        'price_format', 'dark_mode', 'rtl', 'sms_gateway',
        // Login page appearance
        'login_hero_title', 'login_hero_subtitle', 'login_panel_title', 'login_panel_subtitle',
        'login_hero_badge', 'login_hero_feature_1', 'login_hero_feature_2', 'login_hero_feature_3',
        'login_btn_text', 'login_footer_text',
        // Cloud backup settings
        'backup_cloud_enabled', 'backup_keep_local', 'backup_cloud_provider', 'backup_cloud_path',
        'backup_s3_bucket', 'backup_s3_region', 'backup_s3_access_key', 'backup_s3_secret_key',
        'backup_s3_endpoint', 'backup_s3_path_style',
        'backup_gdrive_folder_id', 'backup_gdrive_access_token', 'backup_gdrive_refresh_token',
        'backup_gdrive_client_id', 'backup_gdrive_client_secret',
        'backup_dropbox_path', 'backup_dropbox_access_token',
        'google_calendar_refresh_token', 'google_calendar_calendar_id', 'timezone',
        'google_calendar_client_id', 'google_calendar_client_secret', 'google_calendar_redirect_uri',
    ];

    protected $casts = [
        'currency_id' => 'integer',
        'client_id' => 'integer',
        'quotation_with_stock' => 'integer',
        'show_language' => 'integer',
        'is_invoice_footer' => 'integer',
        'invoice_logo_width' => 'integer',
        'invoice_logo_height' => 'integer',
        'warehouse_id' => 'integer',
        'default_account_id' => 'integer',
        'default_payment_method_id' => 'integer',
        'point_to_amount_rate' => 'double',
        'zatca_enabled' => 'boolean',
        'customize_button_visible' => 'boolean',
        'hide_site_name' => 'boolean',
        'default_tax' => 'double',
        'backup_cloud_enabled' => 'boolean',
        'backup_keep_local' => 'boolean',
        'backup_s3_path_style' => 'boolean',
        'google_calendar_client_secret' => 'encrypted',
        'google_calendar_refresh_token' => 'encrypted',
    ];

    public function Currency()
    {
        return $this->belongsTo('App\Models\Currency');
    }

    public function Client()
    {
        return $this->belongsTo('App\Models\Client');
    }
}
