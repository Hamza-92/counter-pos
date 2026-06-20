<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToSaleDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sale_documents', function (Blueprint $table) {
            $table->foreign('sale_id', 'sale_documents_sale_id_foreign')
                ->references('id')
                ->on('sales')
                ->onUpdate('RESTRICT')
                ->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sale_documents', function (Blueprint $table) {
            $table->dropForeign('sale_documents_sale_id_foreign');
        });
    }
}


















