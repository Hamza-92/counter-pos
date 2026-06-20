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
        Schema::table('products', function (Blueprint $table) {

            // Warranty
            $table->integer('warranty_period')->nullable()->after('type');
            $table->string('warranty_unit')->nullable()->after('warranty_period'); // days|months|years
            $table->text('warranty_terms')->nullable()->after('warranty_unit');

            // Guarantee (if you want to track separately)
            $table->boolean('has_guarantee')->default(false)->after('warranty_terms');
            $table->integer('guarantee_period')->nullable()->after('has_guarantee');
            $table->string('guarantee_unit')->nullable()->after('guarantee_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
