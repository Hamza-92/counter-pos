<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Insert some stuff
        DB::table('permissions')->insert(
            [[
                'id' => 1,
                'name' => 'users_view',
            ],
                [
                    'id' => 2,
                    'name' => 'users_edit',
                ],
                [
                    'id' => 3,
                    'name' => 'record_view',
                ],
                [
                    'id' => 4,
                    'name' => 'users_delete',
                ],
                [
                    'id' => 5,
                    'name' => 'users_add',
                ],
                [
                    'id' => 6,
                    'name' => 'permissions_edit',
                ],
                [
                    'id' => 7,
                    'name' => 'permissions_view',
                ],
                [
                    'id' => 8,
                    'name' => 'permissions_delete',
                ],
                [
                    'id' => 9,
                    'name' => 'permissions_add',
                ],
                [
                    'id' => 10,
                    'name' => 'products_delete',
                ],
                [
                    'id' => 11,
                    'name' => 'products_view',
                ],
                [
                    'id' => 12,
                    'name' => 'barcode_view',
                ],
                [
                    'id' => 13,
                    'name' => 'products_edit',
                ],
                [
                    'id' => 14,
                    'name' => 'products_add',
                ],
                [
                    'id' => 15,
                    'name' => 'expense_add',
                ],
                [
                    'id' => 16,
                    'name' => 'expense_delete',
                ],
                [
                    'id' => 17,
                    'name' => 'expense_edit',
                ],
                [
                    'id' => 18,
                    'name' => 'expense_view',
                ],
                [
                    'id' => 19,
                    'name' => 'transfer_delete',
                ],
                [
                    'id' => 20,
                    'name' => 'transfer_add',
                ],
                [
                    'id' => 21,
                    'name' => 'transfer_view',
                ],
                [
                    'id' => 22,
                    'name' => 'transfer_edit',
                ],
                [
                    'id' => 23,
                    'name' => 'adjustment_delete',
                ],
                [
                    'id' => 24,
                    'name' => 'adjustment_add',
                ],
                [
                    'id' => 25,
                    'name' => 'adjustment_edit',
                ],
                [
                    'id' => 26,
                    'name' => 'adjustment_view',
                ],
                [
                    'id' => 27,
                    'name' => 'Sales_edit',
                ],
                [
                    'id' => 28,
                    'name' => 'Sales_view',
                ],
                [
                    'id' => 29,
                    'name' => 'Sales_delete',
                ],
                [
                    'id' => 30,
                    'name' => 'Sales_add',
                ],
                [
                    'id' => 31,
                    'name' => 'Purchases_edit',
                ],
                [
                    'id' => 32,
                    'name' => 'Purchases_view',
                ],
                [
                    'id' => 33,
                    'name' => 'Purchases_delete',
                ],
                [
                    'id' => 34,
                    'name' => 'Purchases_add',
                ],
                [
                    'id' => 35,
                    'name' => 'Quotations_edit',
                ],
                [
                    'id' => 36,
                    'name' => 'Quotations_delete',
                ],
                [
                    'id' => 37,
                    'name' => 'Quotations_add',
                ],
                [
                    'id' => 38,
                    'name' => 'Quotations_view',
                ],
                [
                    'id' => 39,
                    'name' => 'payment_sales_delete',
                ],
                [
                    'id' => 40,
                    'name' => 'payment_sales_add',
                ],
                [
                    'id' => 41,
                    'name' => 'payment_sales_edit',
                ],
                [
                    'id' => 42,
                    'name' => 'payment_sales_view',
                ],
                [
                    'id' => 43,
                    'name' => 'Purchase_Returns_delete',
                ],
                [
                    'id' => 44,
                    'name' => 'Purchase_Returns_add',
                ],
                [
                    'id' => 45,
                    'name' => 'Purchase_Returns_view',
                ],
                [
                    'id' => 46,
                    'name' => 'Purchase_Returns_edit',
                ],
                [
                    'id' => 47,
                    'name' => 'Sale_Returns_delete',
                ],
                [
                    'id' => 48,
                    'name' => 'Sale_Returns_add',
                ],
                [
                    'id' => 49,
                    'name' => 'Sale_Returns_edit',
                ],
                [
                    'id' => 50,
                    'name' => 'Sale_Returns_view',
                ],
                [
                    'id' => 51,
                    'name' => 'payment_purchases_edit',
                ],
                [
                    'id' => 52,
                    'name' => 'payment_purchases_view',
                ],
                [
                    'id' => 53,
                    'name' => 'payment_purchases_delete',
                ],
                [
                    'id' => 54,
                    'name' => 'payment_purchases_add',
                ],
                [
                    'id' => 55,
                    'name' => 'payment_returns_edit',
                ],
                [
                    'id' => 56,
                    'name' => 'payment_returns_view',
                ],
                [
                    'id' => 57,
                    'name' => 'payment_returns_delete',
                ],
                [
                    'id' => 58,
                    'name' => 'payment_returns_add',
                ],
                [
                    'id' => 59,
                    'name' => 'Customers_edit',
                ],
                [
                    'id' => 60,
                    'name' => 'Customers_view',
                ],
                [
                    'id' => 61,
                    'name' => 'Customers_delete',
                ],
                [
                    'id' => 62,
                    'name' => 'Customers_add',
                ],
                [
                    'id' => 63,
                    'name' => 'unit',
                ],
                [
                    'id' => 64,
                    'name' => 'currency',
                ],
                [
                    'id' => 65,
                    'name' => 'category',
                ],
              
                [
                    'id' => 66,
                    'name' => 'backup',
                ],
                [
                    'id' => 67,
                    'name' => 'warehouse',
                ],
                [
                    'id' => 68,
                    'name' => 'brand',
                ],
                [
                    'id' => 69,
                    'name' => 'setting_system',
                ],
                [
                    'id' => 70,
                    'name' => 'Warehouse_report',
                ],
                [
                    'id' => 71,
                    'name' => 'subcategory',
                ],
                [
                    'id' => 72,
                    'name' => 'Reports_quantity_alerts',
                ],
                [
                    'id' => 73,
                    'name' => 'Reports_profit',
                ],
                [
                    'id' => 74,
                    'name' => 'Reports_suppliers',
                ],
                [
                    'id' => 75,
                    'name' => 'Reports_customers',
                ],
                [
                    'id' => 76,
                    'name' => 'Reports_purchase',
                ],
                [
                    'id' => 77,
                    'name' => 'Reports_sales',
                ],
                [
                    'id' => 78,
                    'name' => 'Reports_payments_purchase_Return',
                ],
                [
                    'id' => 79,
                    'name' => 'Reports_payments_Sale_Returns',
                ],
                [
                    'id' => 80,
                    'name' => 'Reports_payments_Purchases',
                ],
                [
                    'id' => 81,
                    'name' => 'Reports_payments_Sales',
                ],
                [
                    'id' => 82,
                    'name' => 'Suppliers_delete',
                ],
                [
                    'id' => 83,
                    'name' => 'Suppliers_add',
                ],
                [
                    'id' => 84,
                    'name' => 'Suppliers_edit',
                ],
                [
                    'id' => 85,
                    'name' => 'Suppliers_view',
                ],
                [
                    'id' => 86,
                    'name' => 'Pos_view',
                ],
                [
                    'id' => 87,
                    'name' => 'product_import',
                ],
                [
                    'id' => 88,
                    'name' => 'customers_import',
                ],
                [
                    'id' => 89,
                    'name' => 'Suppliers_import',
                ],

                // hrm
                [
                    'id' => 90,
                    'name' => 'view_employee',
                ],
                [
                    'id' => 91,
                    'name' => 'add_employee',
                ],
                [
                    'id' => 92,
                    'name' => 'edit_employee',
                ],
                [
                    'id' => 93,
                    'name' => 'delete_employee',
                ],
                [
                    'id' => 94,
                    'name' => 'company',
                ],
                [
                    'id' => 95,
                    'name' => 'department',
                ],
                [
                    'id' => 96,
                    'name' => 'designation',
                ],
                [
                    'id' => 97,
                    'name' => 'office_shift',
                ],
                [
                    'id' => 98,
                    'name' => 'attendance',
                ],
                [
                    'id' => 99,
                    'name' => 'leave',
                ],
                [
                    'id' => 100,
                    'name' => 'holiday',
                ],
                [
                    'id' => 101,
                    'name' => 'Top_products',
                ],
                [
                    'id' => 102,
                    'name' => 'Top_customers',
                ],
                [
                    'id' => 103,
                    'name' => 'shipment',
                ],
                [
                    'id' => 104,
                    'name' => 'users_report',
                ],
                [
                    'id' => 105,
                    'name' => 'stock_report',
                ],
                [
                    'id' => 106,
                    'name' => 'sms_settings',
                ],
                [
                    'id' => 107,
                    'name' => 'pos_settings',
                ],
                [
                    'id' => 108,
                    'name' => 'payment_gateway',
                ],
                [
                    'id' => 109,
                    'name' => 'mail_settings',
                ],
                [
                    'id' => 110,
                    'name' => 'dashboard',
                ],
                [
                    'id' => 111,
                    'name' => 'pay_due',
                ],
                [
                    'id' => 112,
                    'name' => 'pay_sale_return_due',
                ],
                [
                    'id' => 113,
                    'name' => 'pay_supplier_due',
                ],
                [
                    'id' => 114,
                    'name' => 'pay_purchase_return_due',
                ],
                [
                    'id' => 115,
                    'name' => 'product_report',
                ],
                [
                    'id' => 116,
                    'name' => 'product_sales_report',
                ],
                [
                    'id' => 117,
                    'name' => 'product_purchases_report',
                ],
                [
                    'id' => 118,
                    'name' => 'notification_template',
                ],
                [
                    'id' => 119,
                    'name' => 'edit_product_sale',
                ],
                [
                    'id' => 120,
                    'name' => 'edit_product_purchase',
                ],
                [
                    'id' => 121,
                    'name' => 'edit_product_quotation',
                ],
                [
                    'id' => 122,
                    'name' => 'edit_tax_discount_shipping_sale',
                ],
                [
                    'id' => 123,
                    'name' => 'edit_tax_discount_shipping_purchase',
                ],
                [
                    'id' => 124,
                    'name' => 'edit_tax_discount_shipping_quotation',
                ],
                [
                    'id' => 125,
                    'name' => 'module_settings',
                ],

                [
                    'id' => 126,
                    'name' => 'count_stock',
                ],
                [
                    'id' => 127,
                    'name' => 'deposit_add',
                ],
                [
                    'id' => 128,
                    'name' => 'deposit_delete',
                ],
                [
                    'id' => 129,
                    'name' => 'deposit_edit',
                ],
                [
                    'id' => 130,
                    'name' => 'deposit_view',
                ],
                [
                    'id' => 131,
                    'name' => 'account',
                ],

                [
                    'id' => 132,
                    'name' => 'inventory_valuation',
                ],

                [
                    'id' => 133,
                    'name' => 'expenses_report',
                ],
                [
                    'id' => 134,
                    'name' => 'deposits_report',
                ],
                [
                    'id' => 135,
                    'name' => 'transfer_money',
                ],
                [
                    'id' => 136,
                    'name' => 'payroll',
                ],

                [
                    'id' => 137,
                    'name' => 'projects',
                ],

                [
                    'id' => 138,
                    'name' => 'tasks',
                ],

                [
                    'id' => 139,
                    'name' => 'appearance_settings',
                ],

                [
                    'id' => 140,
                    'name' => 'translations_settings',
                ],

                [
                    'id' => 141,
                    'name' => 'subscription_product',
                ],
                [
                    'id' => 142,
                    'name' => 'report_error_logs',
                ],

                [
                    'id' => 143,
                    'name' => 'payment_methods',
                ],
                [
                    'id' => 144,
                    'name' => 'report_transactions',
                ],

                [
                    'id' => 145,
                    'name' => 'report_sales_by_category',
                ],

                [
                    'id' => 146,
                    'name' => 'report_sales_by_brand',
                ],

                [
                    'id' => 147,
                    'name' => 'opening_stock_import',
                ],

                [
                    'id' => 148,
                    'name' => 'seller_report',
                ],

                [
                    'id' => 149,
                    'name' => 'Store_settings_view',
                ],

                [
                    'id' => 150,
                    'name' => 'Orders_view',
                ],

                [
                    'id' => 151,
                    'name' => 'Collections_view',
                ],

                [
                    'id' => 152,
                    'name' => 'Banners_view',
                ],

                [
                    'id' => 153,
                    'name' => 'inactive_customers_report',
                ],

                [
                    'id' => 154,
                    'name' => 'zeroSalesProducts',
                ],

                [
                    'id' => 155,
                    'name' => 'Dead_Stock_Report',
                ],

                [
                    'id' => 156,
                    'name' => 'draft_invoices_report',
                ],

                [
                    'id' => 157,
                    'name' => 'discount_summary_report',
                ],

                [
                    'id' => 158,
                    'name' => 'tax_summary_report',
                ],

                [
                    'id' => 159,
                    'name' => 'Stock_Aging_Report',
                ],

                [
                    'id' => 160,
                    'name' => 'Stock_Transfer_Report',
                ],

                [
                    'id' => 161,
                    'name' => 'Stock_Adjustment_Report',
                ],

                [
                    'id' => 162,
                    'name' => 'Top_Suppliers_Report',
                ],

                [
                    'id' => 163,
                    'name' => 'Subscribers_view',
                ],

                [
                    'id' => 164,
                    'name' => 'Messages_view',
                ],

                [
                    'id' => 165,
                    'name' => 'cash_register_report',
                ],

                [
                    'id' => 166,
                    'name' => 'woocommerce_settings',
                ],

                [
                    'id' => 167,
                    'name' => 'customer_display_screen_setup',
                ],

                [
                    'id' => 168,
                    'name' => 'quickbooks_settings',
                ],

                [
                    'id' => 169,
                    'name' => 'customer_loyalty_points_report',
                ],

                [
                    'id' => 170,
                    'name' => 'assets',
                ],

                [
                    'id' => 171,
                    'name' => 'damage_view',
                ],

                [
                    'id' => 172,
                    'name' => 'cash_flow_report',
                ],

                [
                    'id' => 173,
                    'name' => 'report_attendance_summary',
                ],

                [
                    'id' => 174,
                    'name' => 'return_ratio_report',
                ],

                [
                    'id' => 175,
                    'name' => 'negative_stock_report',
                ],
                [
                    'id' => 176,
                    'name' => 'accounting_dashboard',
                ],
                [
                    'id' => 177,
                    'name' => 'chart_of_accounts',
                ],
                [
                    'id' => 178,
                    'name' => 'journal_entries',
                ],
                [
                    'id' => 179,
                    'name' => 'trial_balance',
                ],
                [
                    'id' => 180,
                    'name' => 'accounting_profit_loss',
                ],
                [
                    'id' => 181,
                    'name' => 'balance_sheet',
                ],
                [
                    'id' => 182,
                    'name' => 'accounting_tax_report',
                ],
                [
                    'id' => 183,
                    'name' => 'service_jobs',
                ],
                [
                    'id' => 184,
                    'name' => 'service_jobs_report',
                ],
                [
                    'id' => 185,
                    'name' => 'checklist_completion_report',
                ],
                [
                    'id' => 186,
                    'name' => 'customer_maintenance_history_report',
                ],
                [
                    'id' => 187,
                    'name' => 'bookings',
                ],

                [
                    'id' => 188,
                    'name' => 'login_device_management',
                ],

                [
                    'id' => 189,
                    'name' => 'report_device_management',
                ],

                [
                    'id' => 190,
                    'name' => 'update_settings',
                ],

                [
                    'id' => 191,
                    'name' => 'analytics_report',
                ],

                [
                    'id' => 192,
                    'name' => 'Stock_Inventory_Valuation',
                ],

                [
                    'id' => 193,
                    'name' => 'AI_Reports',
                ],

                [
                    'id' => 194,
                    'name' => 'report_warranty',
                ],
                [
                    'id' => 195,
                    'name' => 'system_health_view',
                ],
                [
                    'id' => 196,
                    'name' => 'real_time_sales_counter',
                ],
                [
                    'id' => 197,
                    'name' => 'warehouse_locations',
                ],
                [
                    'id' => 198,
                    'name' => 'internal_location_report',
                ],
                [
                    'id' => 199,
                    'name' => 'contracts',
                ],
                [
                    'id' => 200,
                    'name' => 'commissions_view',
                ],
                [
                    'id' => 201,
                    'name' => 'commissions_add',
                ],
                [
                    'id' => 202,
                    'name' => 'commissions_edit',
                ],
                [
                    'id' => 203,
                    'name' => 'commissions_delete',
                ],
                [
                    'id' => 204,
                    'name' => 'knowledge_base_view',
                ],
                [
                    'id' => 205,
                    'name' => 'webhooks_view',
                ],
                [
                    'id' => 206,
                    'name' => 'webhooks_add',
                ],
                [
                    'id' => 207,
                    'name' => 'webhooks_edit',
                ],
                [
                    'id' => 208,
                    'name' => 'webhooks_delete',
                ],
                [
                    'id' => 209,
                    'name' => 'sales_3d_dashboard',
                ],
                [
                    'id' => 210,
                    'name' => 'batch_view',
                ],
                [
                    'id' => 211,
                    'name' => 'batch_manage',
                ],
                [
                    'id' => 212,
                    'name' => 'batch_writeoff',
                ],
                [
                    'id' => 213,
                    'name' => 'batch_force_override',
                ],
                [
                    'id' => 214,
                    'name' => 'expiry_report',
                ],
                [
                    'id' => 215,
                    'name' => 'Batch_Register_Report',
                ],

            ]
        );
    }
}
