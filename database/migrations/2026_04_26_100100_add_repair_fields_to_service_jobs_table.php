<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            // Device identity
            $table->string('device_brand', 191)->nullable()->after('service_item');
            $table->string('device_model', 191)->nullable()->after('device_brand');
            $table->string('device_serial', 191)->nullable()->after('device_model');
            $table->string('device_imei', 191)->nullable()->after('device_serial');
            $table->string('device_color', 100)->nullable()->after('device_imei');
            $table->string('device_password', 191)->nullable()->after('device_color');
            $table->text('accessories')->nullable()->after('device_password'); // JSON-encoded array

            // Intake / diagnostic
            $table->text('condition_on_arrival')->nullable()->after('accessories');
            $table->text('reported_issue')->nullable()->after('condition_on_arrival');
            $table->text('diagnosis')->nullable()->after('reported_issue');
            $table->float('diagnostic_fee', 10, 0)->default(0)->after('diagnosis');

            // Quote
            $table->float('quote_amount', 10, 0)->default(0)->after('diagnostic_fee');
            $table->date('quote_valid_until')->nullable()->after('quote_amount');
            $table->timestamp('quote_approved_at')->nullable()->after('quote_valid_until');
            $table->string('quote_approved_by', 191)->nullable()->after('quote_approved_at');

            // Totals & payment
            $table->float('total_amount', 10, 0)->default(0)->after('quote_approved_by');
            $table->float('paid_amount', 10, 0)->default(0)->after('total_amount');
            $table->string('payment_status', 50)->default('unpaid')->after('paid_amount'); // unpaid|partial|paid

            // Warranty
            $table->integer('warranty_days')->default(30)->after('payment_status');
            $table->date('warranty_expires_at')->nullable()->after('warranty_days');
            $table->unsignedBigInteger('parent_job_id')->nullable()->after('warranty_expires_at');

            // Delivery
            $table->timestamp('delivered_at')->nullable()->after('parent_job_id');
            $table->string('pickup_signature', 191)->nullable()->after('delivered_at');

            $table->index('payment_status');
            $table->index('parent_job_id');
            $table->index('quote_approved_at');
            $table->index('delivered_at');
            $table->index('warranty_expires_at');
        });
    }

    public function down()
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['parent_job_id']);
            $table->dropIndex(['quote_approved_at']);
            $table->dropIndex(['delivered_at']);
            $table->dropIndex(['warranty_expires_at']);

            $table->dropColumn([
                'device_brand',
                'device_model',
                'device_serial',
                'device_imei',
                'device_color',
                'device_password',
                'accessories',
                'condition_on_arrival',
                'reported_issue',
                'diagnosis',
                'diagnostic_fee',
                'quote_amount',
                'quote_valid_until',
                'quote_approved_at',
                'quote_approved_by',
                'total_amount',
                'paid_amount',
                'payment_status',
                'warranty_days',
                'warranty_expires_at',
                'parent_job_id',
                'delivered_at',
                'pickup_signature',
            ]);
        });
    }
};
