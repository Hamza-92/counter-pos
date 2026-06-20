<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosSetting extends Model
{
    protected $fillable = [
        'note_customer', 'show_logo', 'logo_size', 'show_store_name',
        'show_reference', 'show_date', 'show_seller',
        'show_note', 'show_barcode', 'show_discount', 'show_tax', 'show_shipping', 'show_customer',
        'show_email', 'show_phone', 'show_address', 'is_printable', 'show_Warehouse', 'products_per_page',
        'quick_add_customer', 'barcode_scanning_sound', 'show_product_images',
        'show_stock_quantity', 'enable_hold_sales', 'enable_customer_points', 'show_categories', 'show_brands',
        'allow_overselling',
        'receipt_layout', 'receipt_paper_size',
        'show_paid', 'show_due', 'show_payments', 'show_zatca_qr',
        'cash_drawer_auto_open', 'cash_drawer_printer_name',
        'direct_network_printing', 'network_printer_ip', 'network_printer_port',
    ];

    protected $casts = [
        'show_logo' => 'integer',
        'show_store_name' => 'integer',
        'show_reference' => 'integer',
        'show_date' => 'integer',
        'show_seller' => 'integer',
        'show_note' => 'integer',
        'show_barcode' => 'integer',
        'show_discount' => 'integer',
        'show_tax' => 'integer',
        'show_shipping' => 'integer',
        'show_customer' => 'integer',
        'show_Warehouse' => 'integer',
        'show_email' => 'integer',
        'show_phone' => 'integer',
        'show_address' => 'integer',
        'is_printable' => 'integer',
        'products_per_page' => 'integer',
        'quick_add_customer' => 'integer',
        'barcode_scanning_sound' => 'integer',
        'show_product_images' => 'integer',
        'show_stock_quantity' => 'integer',
        'enable_hold_sales' => 'integer',
        'enable_customer_points' => 'integer',
        'show_categories' => 'integer',
        'show_brands' => 'integer',
        'allow_overselling' => 'boolean',
        'receipt_layout' => 'integer',
        'receipt_paper_size' => 'integer',
        'logo_size' => 'integer',
        'show_paid' => 'integer',
        'show_due' => 'integer',
        'show_payments' => 'integer',
        'show_zatca_qr' => 'integer',
        'cash_drawer_auto_open' => 'boolean',
        'direct_network_printing' => 'boolean',
        'network_printer_port' => 'integer',
    ];
}
