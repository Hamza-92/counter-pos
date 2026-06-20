<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $replacements = [
            'CompanyName' => ['Stocky', 'Counter POS'],
            'footer' => ['Stocky - Ultimate Inventory With POS', 'Counter POS - Point of Sale & Inventory'],
            'developed_by' => ['Stocky', 'Counter POS'],
            'app_name' => ['Stocky | Ultimate Inventory With POS', 'Counter POS'],
            'page_title_suffix' => ['Ultimate Inventory With POS', 'Point of Sale & Inventory'],
        ];

        foreach ($replacements as $column => [$old, $new]) {
            if (! Schema::hasColumn('settings', $column)) {
                continue;
            }

            DB::table('settings')
                ->where($column, $old)
                ->update([$column => $new]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $replacements = [
            'CompanyName' => ['Counter POS', 'Stocky'],
            'footer' => ['Counter POS - Point of Sale & Inventory', 'Stocky - Ultimate Inventory With POS'],
            'developed_by' => ['Counter POS', 'Stocky'],
            'app_name' => ['Counter POS', 'Stocky | Ultimate Inventory With POS'],
            'page_title_suffix' => ['Point of Sale & Inventory', 'Ultimate Inventory With POS'],
        ];

        foreach ($replacements as $column => [$old, $new]) {
            if (! Schema::hasColumn('settings', $column)) {
                continue;
            }

            DB::table('settings')
                ->where($column, $old)
                ->update([$column => $new]);
        }
    }
};
