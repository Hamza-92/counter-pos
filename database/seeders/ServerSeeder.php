<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Insert some stuff
        DB::table('servers')->insert(
            [
                'id' => 1,
                'host' => 'mailtrap.io',
                'port' => '2525',
                'username' => 'test',
                'password' => 'test',
                'encryption' => 'tls',
            ]

        );
    }
}
