<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Insert some stuff
        DB::table('settings')->insert(
            [
                'id' => 1,
                'email' => 'admin@example.com',
                'currency_id' => 1,
                'client_id' => 1,
                'sms_gateway' => 1,
                'point_to_amount_rate' => 1,
                'is_invoice_footer' => 0,
                'invoice_footer' => null,
                'warehouse_id' => null,
                'CompanyName' => 'Stocky',
                'CompanyPhone' => '6315996770',
                'CompanyAdress' => '3618 Abia Martin Drive',
                'footer' => 'Stocky - Ultimate Inventory With POS',
                'developed_by' => 'Stocky',
                'logo' => 'logo-default.png',
                'app_name' => 'Stocky | Ultimate Inventory With POS',
                'page_title_suffix' => 'Ultimate Inventory With POS',
                'favicon' => 'favicon.ico',
                'default_language' => 'en',
                'quotation_with_stock' => 1,
                'show_language' => 1,
                'default_tax' => 0,
            ]

        );
    }
}
