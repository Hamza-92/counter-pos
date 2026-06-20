<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('report_questions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->string('title');
            $table->string('report_key'); // e.g. daily_sales_summary, sales_by_product, late_payments
            $table->json('default_filters')->nullable(); // e.g. { "range": "yesterday", "limit": 10, "sort_by": "profit", "sort_dir": "desc" }
            $table->json('default_compare')->nullable(); // e.g. { "range": "previous_day" }
            $table->boolean('needs_insights')->default(false);
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps(6);
        });

        // Insert default questions
        $questions = [
            [
                'title' => 'Yesterday Sales Summary',
                'report_key' => 'daily_sales_summary',
                'default_filters' => json_encode(['range' => 'yesterday']),
                'default_compare' => null,
                'needs_insights' => false,
                'active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Why profit dropped yesterday?',
                'report_key' => 'daily_sales_summary',
                'default_filters' => json_encode(['range' => 'yesterday']),
                'default_compare' => json_encode(['range' => 'previous_day']),
                'needs_insights' => true,
                'active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Top 10 products by profit (this week)',
                'report_key' => 'sales_by_product',
                'default_filters' => json_encode([
                    'range' => 'this_week',
                    'limit' => 10,
                    'sort_by' => 'profit',
                    'sort_dir' => 'desc',
                ]),
                'default_compare' => null,
                'needs_insights' => false,
                'active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Customers with late payments > 30 days',
                'report_key' => 'late_payments',
                'default_filters' => json_encode(['min_days_overdue' => 30]),
                'default_compare' => null,
                'needs_insights' => false,
                'active' => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('report_questions')->insert($questions);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_questions');
    }
};
