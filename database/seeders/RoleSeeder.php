<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Insert some stuff
        DB::table('roles')->insert(
            [[
                'id' => 1,
                'name' => 'Owner',
                'label' => 'Owner',
                'status' => 1,
                'description' => 'Owner',
            ]]
        );
    }
}
