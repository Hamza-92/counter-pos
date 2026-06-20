<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToPurchaseDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->foreign('purchase_id', 'purchase_documents_purchase_id_foreign')
                ->references('id')
                ->on('purchases')
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
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropForeign('purchase_documents_purchase_id_foreign');
        });
    }
}

