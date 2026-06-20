<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\sms_gateway;
use Auth;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class UpdateController extends Controller
{
    public function get_version_info(Request $request)
    {
        $currentVersion = trim(File::get(base_path('version.txt')));

        // Fetch latest version info from update server
        $latestVersion = '';
        $latestInfo = null;
        $changelog = [];

        try {
            $ctx = stream_context_create([
                'http' => ['timeout' => 10],
                'https' => ['timeout' => 10],
            ]);
            $json = @file_get_contents('https://update-stocky.ui-lib.com/stocky_version.json', false, $ctx);
            if ($json !== false) {
                $latestInfo = json_decode($json, true);
                $latestVersion = $latestInfo['version'] ?? '';

                // Build changelog from remote data if available
                if (!empty($latestInfo['changelog'])) {
                    $changelog = $latestInfo['changelog'];
                } elseif (!empty($latestInfo['release_notes'])) {
                    $changelog = [
                        [
                            'version' => $latestVersion,
                            'date' => $latestInfo['date'] ?? now()->toDateString(),
                            'items' => array_map(function ($note) {
                                if (is_string($note)) {
                                    return ['type' => 'misc', 'text' => $note];
                                }
                                return $note;
                            }, (array) $latestInfo['release_notes']),
                        ],
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Silently fail - we'll just show current version
        }

        // Load update history
        $updateHistory = [];
        $historyFile = storage_path('app/update_history.json');
        if (File::exists($historyFile)) {
            $updateHistory = json_decode(File::get($historyFile), true) ?: [];
            $updateHistory = array_reverse($updateHistory);
        }

        return response()->json([
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'latest_info' => $latestInfo,
            'changelog' => $changelog,
            'update_history' => array_slice($updateHistory, 0, 10),
        ]);
    }

    public function viewStep1(Request $request)
    {
        $role = Auth::user()->roles()->first();
        $permission = Role::findOrFail($role->id)->inRole('setting_system');
        if ($permission) {
            return view('update.viewStep1');
        }
    }

    public function lastStep(Request $request)
    {
        ini_set('max_execution_time', 2000); 
		ini_set('memory_limit', '512M');
        
        $role = Auth::user()->roles()->first();
        $permission = Role::findOrFail($role->id)->inRole('setting_system');

        if ($permission) {
            ini_set('max_execution_time', 2000);

            try {

                Artisan::call('config:cache');
                Artisan::call('config:clear');

                // ----------------------------------------------------
                // ✅ Backward compatibility for old sales (NO discount_method)
                // ----------------------------------------------------
                 if (!Schema::hasColumn('sales', 'discount_method')) {

                    // Run ONLY if the old loyalty column exists
                    if (Schema::hasColumn('sales', 'discount_from_points')) {

                        DB::table('sales')
                            ->where('discount_from_points', '>', 0)
                            ->update([
                                'discount' => DB::raw('GREATEST(discount - discount_from_points, 0)')
                            ]);
                    }
                }


                Artisan::call('migrate --force');

                $role = Role::findOrFail(1);
                $role->permissions()->detach();

                $permissions = [
                    0 => 'view_employee',
                    1 => 'add_employee',
                    2 => 'edit_employee',
                    3 => 'delete_employee',
                    4 => 'company',
                    5 => 'department',
                    6 => 'designation',
                    7 => 'office_shift',
                    8 => 'attendance',
                    9 => 'leave',
                    10 => 'holiday',
                    11 => 'Top_products',
                    12 => 'Top_customers',
                    13 => 'shipment',
                    14 => 'users_report',
                    15 => 'stock_report',
                    16 => 'sms_settings',
                    17 => 'pos_settings',
                    18 => 'payment_gateway',
                    19 => 'mail_settings',
                    20 => 'dashboard',
                    21 => 'pay_due',
                    22 => 'pay_sale_return_due',
                    23 => 'pay_supplier_due',
                    24 => 'pay_purchase_return_due',
                    25 => 'product_report',
                    26 => 'product_sales_report',
                    27 => 'product_purchases_report',
                    28 => 'notification_template',
                    29 => 'edit_product_sale',
                    30 => 'edit_product_purchase',
                    31 => 'edit_product_quotation',
                    32 => 'edit_tax_discount_shipping_sale',
                    33 => 'edit_tax_discount_shipping_purchase',
                    34 => 'edit_tax_discount_shipping_quotation',
                    35 => 'module_settings',
                    36 => 'count_stock',
                    37 => 'deposit_add',
                    38 => 'deposit_delete',
                    39 => 'deposit_edit',
                    40 => 'deposit_view',
                    41 => 'account',
                    42 => 'inventory_valuation',
                    43 => 'expenses_report',
                    44 => 'deposits_report',
                    45 => 'transfer_money',
                    46 => 'payroll',
                    47 => 'projects',
                    48 => 'tasks',
                    49 => 'appearance_settings',
                    50 => 'translations_settings',
                    51 => 'subscription_product',
                    52 => 'report_error_logs',
                    53 => 'payment_methods',
                    54 => 'report_transactions',
                    55 => 'report_sales_by_category',
                    56 => 'report_sales_by_brand',
                    57 => 'opening_stock_import',
                    58 => 'seller_report',
                    59 => 'Store_settings_view',
                    60 => 'Orders_view',
                    61 => 'Collections_view',
                    62 => 'Banners_view',
                    63 => 'inactive_customers_report',
                    64 => 'zeroSalesProducts',
                    65 => 'Dead_Stock_Report',
                    66 => 'draft_invoices_report',
                    67 => 'discount_summary_report',
                    68 => 'tax_summary_report',
                    69 => 'Stock_Aging_Report',
                    70 => 'Stock_Transfer_Report',
                    71 => 'Stock_Adjustment_Report',
                    72 => 'Top_Suppliers_Report',
                    73 => 'Subscribers_view',
                    74 => 'Messages_view',
                    75 => 'cash_register_report',
                    76 => 'woocommerce_settings',
                    77 => 'customer_display_screen_setup',
                    78 => 'quickbooks_settings',
                    79 => 'customer_loyalty_points_report',
                    80 => 'assets',
                    81 => 'damage_view',
                    82 => 'cash_flow_report',
                    83 => 'report_attendance_summary',
                    84 => 'return_ratio_report',
                    85 => 'negative_stock_report',
                    86 => 'accounting_dashboard',
                    87 => 'chart_of_accounts',
                    88 => 'journal_entries',
                    89 => 'trial_balance',
                    90 => 'accounting_profit_loss',
                    91 => 'balance_sheet',
                    92 => 'accounting_tax_report',
                    93 => 'service_jobs',
                    94 => 'service_jobs_report',
                    95 => 'checklist_completion_report',
                    96 => 'customer_maintenance_history_report',
                    97 => 'bookings',
                    98 => 'subcategory',
                    99 => 'login_device_management',
                    100 => 'report_device_management',
                    101 => 'update_settings',
                    102 => 'analytics_report',
                    103 => 'Stock_Inventory_Valuation',
                    104 => 'AI_Reports',
                    105 => 'report_warranty',
                    106 => 'system_health_view', 
                    107 => 'real_time_sales_counter',
                    108 => 'warehouse_locations',
                    109 => 'internal_location_report',
                    110 => 'contracts',
                    111 => 'commissions_view',
                    112 => 'commissions_add',
                    113 => 'commissions_edit',
                    114 => 'commissions_delete',
                    115 => 'knowledge_base_view',
                    116 => 'webhooks_view',
                    117 => 'webhooks_add',
                    118 => 'webhooks_edit',
                    119 => 'webhooks_delete',
                    120 => 'sales_3d_dashboard',
                    121 => 'batch_view',
                    122 => 'batch_manage',
                    123 => 'batch_writeoff',
                    124 => 'batch_force_override',
                    125 => 'expiry_report',
                    126 => 'Batch_Register_Report',

                ];

                foreach ($permissions as $permission_slug) {
                    $perm = Permission::firstOrCreate(['name' => $permission_slug]);
                }

                $permissions_data = Permission::pluck('id')->toArray();
                $role->permissions()->attach($permissions_data);

                // create new sms gateway infobip
                sms_gateway::firstOrCreate(['title' => 'infobip']);
                sms_gateway::firstOrCreate(['title' => 'termii']); // ✅ Create "termii" gateway
                sms_gateway::firstOrCreate(['title' => 'custom']); // ✅ Create "custom" gateway

                // Check if the SMS gateway with the title 'nexmo' exists
                $nexmoGateway = sms_gateway::where('title', 'nexmo')->first();

                if ($nexmoGateway) {
                    // If it exists, delete it
                    $nexmoGateway->delete();
                }

                // Insert SMS message template: subscription_reminder
                DB::table('sms_messages')->updateOrInsert(
                    [
                        'name' => 'subscription_reminder',
                        'text' => "Hello {client_name},\nThis is a reminder from {business_name} that your subscription will renew automatically on {next_billing_date}. \nPlease ensure your payment method is up-to-date to avoid interruptions.\nThank you!",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                // Insert SMS message template: asset_validation_due (tags: {asset_name},{asset_tag},{next_validation},{business_name})
                $smsAssetValidationKeys = ['name' => 'asset_validation_due'];
                if (Schema::hasColumn('sms_messages', 'locale')) {
                    $smsAssetValidationKeys['locale'] = 'en';
                }
                DB::table('sms_messages')->updateOrInsert(
                    $smsAssetValidationKeys,
                    [
                        'text' => "Reminder from {business_name}: Asset {asset_name} ({asset_tag}) is due for validation on {next_validation}. Please verify the tool or equipment.",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                // Insert email message template: booking
                DB::table('email_messages')->updateOrInsert(
                    ['name' => 'booking'],
                    [
                        'subject' => 'Your Booking Confirmation',
                        'body' => '<p><b><span style="font-size:14px;">Dear {contact_name},</span></b></p><p>Your booking has been confirmed. Booking number: {booking_number}.</p><p><span style="font-size:14px;">Date: {booking_date}<br>Time: {start_time} - {end_time}<br>Service: {service_name}</span></p><p>If you have any questions, please don\'t hesitate to contact us.</p><p>Best regards,</p><p><b><span style="font-size:14px;">{business_name}</span></b></p>',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                // Insert email message template: asset_validation_due (tags: {asset_name},{asset_tag},{next_validation},{asset_edit_url},{business_name})
                $emailAssetValidationKeys = ['name' => 'asset_validation_due'];
                if (Schema::hasColumn('email_messages', 'locale')) {
                    $emailAssetValidationKeys['locale'] = 'en';
                }
                DB::table('email_messages')->updateOrInsert(
                    $emailAssetValidationKeys,
                    [
                        'subject' => 'Asset validation due: {asset_name}',
                        'body' => '<p>The following asset is due for validation (or is overdue). Please verify the tool or equipment.</p><p><b>Asset:</b> {asset_name} ({asset_tag})<br><b>Next validation date:</b> {next_validation}</p><p><a href="{asset_edit_url}">View / Edit asset</a></p><p>Best regards,<br><b>{business_name}</b></p>',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                // ✅ Run TranslationSeeder automatically
                Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\TranslationSeeder',
                    '--force' => true,
                ]);

                // ✅ Run StoreSettingSeeder automatically
                Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\StoreSettingSeeder',
                    '--force' => true,
                ]);

                // ----------------------------------------------------
                // ✅ Clean product names containing < or >
                // ----------------------------------------------------
                $cleaned = 0;
                \App\Models\Product::where('name', 'REGEXP', '<|>')
                    ->chunkById(200, function ($products) use (&$cleaned) {
                        foreach ($products as $p) {
                            $old = $p->name;
                            // Replace dangerous characters or strip HTML
                            $clean = str_replace(['<', '>'], ['‹', '›'], strip_tags($old));
                            if ($clean !== $old) {
                                $p->name = $clean;
                                $p->save();
                                $cleaned++;
                            }
                        }
                    });

                // ✅ Clear caches so translations are picked up immediately
                Artisan::call('cache:clear');
                Artisan::call('config:clear');
                Artisan::call('view:clear');
                Artisan::call('route:clear');

            } catch (\Exception $e) {

                return $e->getMessage();

                return 'Something went wrong';
            }

            return view('update.finishedUpdate');
        }
    }
}
