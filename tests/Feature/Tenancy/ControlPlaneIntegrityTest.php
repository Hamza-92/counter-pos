<?php

namespace Tests\Feature\Tenancy;

use App\Models\ControlPlane\ControlAuditLog;
use App\Models\ControlPlane\ManualPayment;
use Illuminate\Support\Facades\Schema;
use LogicException;

class ControlPlaneIntegrityTest extends ControlPlaneTestCase
{
    public function test_the_control_schema_is_isolated_and_complete(): void
    {
        foreach ([
            'super_admins', 'tenants', 'domains', 'tenant_databases', 'plans',
            'subscriptions', 'manual_payments', 'provisioning_runs',
            'control_audit_logs', 'sessions', 'cache', 'jobs', 'failed_jobs',
        ] as $table) {
            $this->assertTrue(Schema::connection('control')->hasTable($table), $table.' is missing');
        }
    }

    public function test_audit_logs_are_append_only(): void
    {
        $log = ControlAuditLog::create(['action' => 'tenant.created']);

        $this->expectException(LogicException::class);
        $log->update(['action' => 'tenant.changed']);
    }

    public function test_manual_payments_are_append_only_at_the_model_boundary(): void
    {
        $payment = new ManualPayment;
        $payment->exists = true;

        $this->expectException(LogicException::class);
        $payment->delete();
    }
}
