<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_settings', 'direct_network_printing')) {
                $table->boolean('direct_network_printing')
                    ->default(0)
                    ->after('cash_drawer_printer_name');
            }
            if (!Schema::hasColumn('pos_settings', 'network_printer_ip')) {
                $table->string('network_printer_ip', 64)
                    ->nullable()
                    ->after('direct_network_printing');
            }
            if (!Schema::hasColumn('pos_settings', 'network_printer_port')) {
                $table->unsignedSmallInteger('network_printer_port')
                    ->nullable()
                    ->default(9100)
                    ->after('network_printer_ip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_settings', function (Blueprint $table) {
            foreach (['network_printer_port', 'network_printer_ip', 'direct_network_printing'] as $column) {
                if (Schema::hasColumn('pos_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
