<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddCustomSmsGateway extends Migration
{
    public function up()
    {
        $exists = DB::table('sms_gateway')->where('title', 'custom')->exists();

        if (! $exists) {
            DB::table('sms_gateway')->insert([
                'title' => 'custom',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        DB::table('sms_gateway')->where('title', 'custom')->delete();
    }
}
