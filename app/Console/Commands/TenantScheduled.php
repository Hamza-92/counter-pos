<?php

namespace App\Console\Commands;

use App\Models\ControlPlane\Tenant;
use App\Tenancy\TenantOperationRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantScheduled extends Command
{
    protected $signature = 'tenant:scheduled {task : assets, invoices, or reminders}';
    protected $description = 'Run one allowlisted scheduled task in isolation for each eligible tenant';

    public function handle(TenantOperationRunner $runner): int
    {
        $commands = [
            'assets' => 'assets:check-validation-due',
            'invoices' => 'subscriptions:generate-invoices',
            'reminders' => 'subscriptions:send-sms-reminders',
        ];
        $task = (string) $this->argument('task');
        if (! isset($commands[$task])) {
            $this->error('Unknown scheduled task.');

            return self::FAILURE;
        }

        $failed = 0;
        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant) use ($runner, $commands, $task, &$failed) {
            try {
                $runner->run($tenant->id, 'scheduled:'.$task, function () use ($commands, $task) {
                    $exit = Artisan::call($commands[$task]);
                    if ($exit !== 0) {
                        throw new \RuntimeException('Scheduled tenant command failed.');
                    }

                    return ['command' => $commands[$task]];
                });
            } catch (\Throwable $exception) {
                $failed++;
                $this->error($tenant->id.': '.$exception->getMessage());
            }
        });

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
