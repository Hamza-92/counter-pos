<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsSubcategoryToCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // No-op: earlier experiment for flagging subcategories on categories.
        // The final design uses a dedicated `subcategories` table instead,
        // so we intentionally do not modify the `categories` table here.
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // No-op – see comment in up().
    }
}


