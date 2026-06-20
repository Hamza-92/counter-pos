<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Replace report_questions with 20 questions.
     */
    public function up(): void
    {
        DB::table('report_questions')->truncate();

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
                'title' => 'Why did profit drop yesterday?',
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
                'title' => "Today's Sales Summary",
                'report_key' => 'daily_sales_summary',
                'default_filters' => json_encode(['range' => 'today']),
                'default_compare' => null,
                'needs_insights' => false,
                'active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'This Week Sales Summary',
                'report_key' => 'daily_sales_summary',
                'default_filters' => json_encode(['range' => 'this_week']),
                'default_compare' => null,
                'needs_insights' => false,
                'active' => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Last Week Sales Summary',
                'report_key' => 'daily_sales_summary',
                'default_filters' => json_encode(['range' => 'last_week']),
                'default_compare' => null,
                'needs_insights' => false,
                'active' => true,
                'sort_order' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'This Week vs Last Week (Insights)',
                'report_key' => 'daily_sales_summary',
                'default_filters' => json_encode(['range' => 'this_week']),
                'default_compare' => json_encode(['range' => 'previous_week']),
                'needs_insights' => true,
                'active' => true,
                'sort_order' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'This Month Sales Summary',
                'report_key' => 'daily_sales_summary',
                'default_filters' => json_encode(['range' => 'this_month']),
                'default_compare' => null,
                'needs_insights' => false,
                'active' => true,
                'sort_order' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Last Month Sales Summary',
                'report_key' => 'daily_sales_summary',
                'default_filters' => json_encode(['range' => 'last_month']),
                'default_compare' => null,
                'needs_insights' => false,
                'active' => true,
                'sort_order' => 8,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Top 10 Products by Profit (This Week)',
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
                'sort_order' => 9,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Top 20 Products by Profit (This Month)',
                'report_key' => 'sales_by_product',
                'default_filters' => json_encode([
                    'range' => 'this_month',
                    'limit' => 20,
                    'sort_by' => 'profit',
                    'sort_dir' => 'desc',
                ]),
                'default_compare' => null,
                'needs_insights' => false,
                'active' => true,
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Top 15 Products by Revenue (This Week)',
                'report_key' => 'sales_by_product',
                'default_filters' => json_encode([
                    'range' => 'this_week',
                    'limit' => 15,
                    'sort_by' => 'revenue',
                    'sort_dir' => 'desc',
                ]),
                'default_compare' => null,
                'needs_insights' => false,
                'active' => true,
                'sort_order' => 11,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Top 10 Products by Quantity Sold (This Week)',
                'report_key' => 'sales_by_product',
                'default_filters' => json_encode([
                    'range' => 'this_week',
                    'limit' => 10,
                    'sort_by' => 'qty',
                    'sort_dir' => 'desc',
                ]),
                'default_compare' => null,
                'needs_insights' => false,
                'active' => true,
                'sort_order' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Top 10 Products by Profit (Last Month)',
                'report_key' => 'sales_by_product',
                'default_filters' => json_encode([
                    'range' => 'last_month',
                    'limit' => 10,
                    'sort_by' => 'profit',
                    'sort_dir' => 'desc',
                ]),
                'default_compare' => null,
                'needs_insights' => false,
                'active' => true,
                'sort_order' => 13,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Customers with Late Payments > 30 Days',
                'report_key' => 'late_payments',
                'default_filters' => json_encode(['min_days_overdue' => 30]),
                'default_compare' => null,
                'needs_insights' => false,
                'active' => true,
                'sort_order' => 14,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Customers with Late Payments > 60 Days',
                'report_key' => 'late_payments',
                'default_filters' => json_encode(['min_days_overdue' => 60]),
                'default_compare' => null,
                'needs_insights' => false,
                'active' => true,
                'sort_order' => 15,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Customers with Late Payments > 90 Days',
                'report_key' => 'late_payments',
                'default_filters' => json_encode(['min_days_overdue' => 90]),
                'default_compare' => null,
                'needs_insights' => false,
                'active' => true,
                'sort_order' => 16,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Overdue Invoices (7+ Days)',
                'report_key' => 'late_payments',
                'default_filters' => json_encode(['min_days_overdue' => 7]),
                'default_compare' => null,
                'needs_insights' => false,
                'active' => true,
                'sort_order' => 17,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Overdue Invoices (14+ Days)',
                'report_key' => 'late_payments',
                'default_filters' => json_encode(['min_days_overdue' => 14]),
                'default_compare' => null,
                'needs_insights' => false,
                'active' => true,
                'sort_order' => 18,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Top 25 Products by Profit (This Month)',
                'report_key' => 'sales_by_product',
                'default_filters' => json_encode([
                    'range' => 'this_month',
                    'limit' => 25,
                    'sort_by' => 'profit',
                    'sort_dir' => 'desc',
                ]),
                'default_compare' => null,
                'needs_insights' => false,
                'active' => true,
                'sort_order' => 19,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Yesterday vs Day Before (Profit Insights)',
                'report_key' => 'daily_sales_summary',
                'default_filters' => json_encode(['range' => 'yesterday']),
                'default_compare' => json_encode(['range' => 'previous_day']),
                'needs_insights' => true,
                'active' => true,
                'sort_order' => 20,
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
        DB::table('report_questions')->truncate();

        // Re-insert original 4 questions
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
};
