<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'firstname')) {
                $table->string('firstname')->nullable()->after('name');
            }
            if (!Schema::hasColumn('clients', 'lastname')) {
                $table->string('lastname')->nullable()->after('firstname');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Drop in reverse order to satisfy some SQL engines
            if (Schema::hasColumn('clients', 'lastname')) {
                $table->dropColumn('lastname');
            }
            if (Schema::hasColumn('clients', 'firstname')) {
                $table->dropColumn('firstname');
            }
        });
    }
};

