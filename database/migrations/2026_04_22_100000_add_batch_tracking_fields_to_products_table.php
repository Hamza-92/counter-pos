<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBatchTrackingFieldsToProductsTable extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'is_batch_tracked')) {
                $table->boolean('is_batch_tracked')->default(false)->after('type');
            }
            if (! Schema::hasColumn('products', 'shelf_life_days')) {
                $table->integer('shelf_life_days')->nullable()->after('is_batch_tracked');
            }
            if (! Schema::hasColumn('products', 'generic_name')) {
                $table->string('generic_name', 191)->nullable()->after('shelf_life_days');
            }
            if (! Schema::hasColumn('products', 'strength')) {
                $table->string('strength', 100)->nullable()->after('generic_name');
            }
            if (! Schema::hasColumn('products', 'dosage_form')) {
                $table->string('dosage_form', 100)->nullable()->after('strength');
            }
            if (! Schema::hasColumn('products', 'pack_size')) {
                $table->string('pack_size', 100)->nullable()->after('dosage_form');
            }
            if (! Schema::hasColumn('products', 'manufacturer')) {
                $table->string('manufacturer', 191)->nullable()->after('pack_size');
            }
            if (! Schema::hasColumn('products', 'prescription_required')) {
                $table->boolean('prescription_required')->default(false)->after('manufacturer');
            }
            if (! Schema::hasColumn('products', 'drug_schedule')) {
                $table->string('drug_schedule', 50)->nullable()->after('prescription_required');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'is_batch_tracked')) {
                $table->index('is_batch_tracked', 'products_is_batch_tracked_index');
            }
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'is_batch_tracked')) {
                try { $table->dropIndex('products_is_batch_tracked_index'); } catch (\Throwable $e) {}
            }
            $cols = [
                'is_batch_tracked', 'shelf_life_days', 'generic_name', 'strength',
                'dosage_form', 'pack_size', 'manufacturer', 'prescription_required', 'drug_schedule',
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('products', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
}
