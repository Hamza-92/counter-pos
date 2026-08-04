<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
        'App\Console\Commands\DatabaseBackUp',
        'App\Console\Commands\WooCommerceSync',
        'App\\Console\\Commands\\WooCommercePushProducts',
    ];

    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {

        if (config('tenancy.enabled', false)) {
            $schedule->command('queue:work database --once --queue=default --sleep=1 --tries=1 --timeout='.((int) env('QUEUE_WORKER_TIMEOUT', 1200)))
                ->everyMinute()->withoutOverlapping()->evenInMaintenanceMode();
            $schedule->command('tenant:scheduled assets')->daily()->withoutOverlapping();
            $schedule->command('tenant:scheduled invoices')->dailyAt('00:10')->withoutOverlapping();
            $schedule->command('tenant:scheduled reminders')->dailyAt('09:00')->withoutOverlapping();

            return;
        }

        $schedule->command('database:backup');

        $schedule->command('assets:check-validation-due')->daily();

        /**
         * Shared hosting friendly queue processing:
         * Run a single queue job each minute (database driver).
         *
         * IMPORTANT: You must have a cron that runs `php artisan schedule:run` every minute.
         * This prevents Woo sync batches from getting stuck at "queued_next_batch" when no
         * long-running queue worker (Supervisor/systemd) is available.
         */
        $schedule->command('queue:work database --once --queue=default --sleep=1 --tries=1 --timeout='.((int) env('QUEUE_WORKER_TIMEOUT', 1200)))
            ->everyMinute()
            ->withoutOverlapping()
            ->evenInMaintenanceMode();

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
