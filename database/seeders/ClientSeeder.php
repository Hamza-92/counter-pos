<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('clients')->insert([
            'id' => 1,
            'name' => 'walk-in-customer',
            'code' => 1,
            'email' => 'walk-in-customer@example.com',
            'country' => 'bangladesh',
            'city' => 'dhaka',
            'phone' => '123456780',
            'adresse' => 'N45 , Dhaka',
            'tax_number' => null,
            'is_royalty_eligible' => 1,
            'points' => 0,
        ]);
    }
}
