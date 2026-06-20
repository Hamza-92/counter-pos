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
        Schema::table('sales', function (Blueprint $table) {
            $table->integer('subscription_id')->nullable()->after('warehouse_id')->index('subscription_id');

            $table->foreign('subscription_id', 'sales_subscription_id_foreign')
                ->references('id')
                ->on('subscriptions')
                ->onUpdate('RESTRICT')
                ->onDelete('RESTRICT');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign('sales_subscription_id_foreign');
            $table->dropIndex('subscription_id');
            $table->dropColumn('subscription_id');
        });
    }
};
