<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $paymentMethods = [
            ['id' => 1, 'name' => 'Credit Card'],
            ['id' => 2, 'name' => 'Cash'],
            ['id' => 3, 'name' => 'Check'],
            ['id' => 4, 'name' => 'TPE'],
            ['id' => 5, 'name' => 'Western Union'],
            ['id' => 6, 'name' => 'bank transfer'],
            ['id' => 7, 'name' => 'other'],
        ];

        foreach ($paymentMethods as $method) {
            DB::table('payment_methods')->updateOrInsert(['id' => $method['id']], $method);
        }
    }
}
