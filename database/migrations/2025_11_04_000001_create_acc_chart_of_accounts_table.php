<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NEW FEATURE - SAFE ADDITION
 *
 * Creates hierarchical Chart of Accounts table without altering existing tables.
 * Uses nullable links to existing `accounts` to avoid hard dependencies.
 */
class CreateAccChartOfAccountsTable extends Migration
{
    public function up()
    {
        Schema::create('acc_chart_of_accounts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->integer('account_id')->nullable()->index(); // optional link to legacy accounts
            $table->string('code', 64);
            $table->string('name', 192);
            $table->string('type', 32)->index(); // asset, liability, equity, income, expense
            $table->integer('parent_id')->nullable()->index();
            $table->smallInteger('level')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);
        });
    }

    public function down()
    {
        Schema::dropIfExists('acc_chart_of_accounts');
    }
}





