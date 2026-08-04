<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ControlMigrate extends Command
{
    protected $signature = 'control:migrate {--confirm-control : Confirm this targets only the configured control database}';

    protected $description = 'Run only the isolated control-plane migrations';

    public function handle(): int
    {
        if (! $this->option('confirm-control')) {
            $this->error('Refusing to run without --confirm-control.');
            return self::FAILURE;
        }

        $connection = DB::connection('control');
        if ($connection->getDriverName() !== 'mysql') {
            $this->error('Production control migrations require the configured MySQL control connection.');
            return self::FAILURE;
        }

        $expected = (string) config('database.connections.control.database');
        $selected = $connection->selectOne('SELECT DATABASE() AS database_name')->database_name ?? null;

        if ($expected === '' || $selected === null || ! hash_equals($expected, $selected)) {
            $this->error('The selected database does not match CONTROL_DB_DATABASE.');
            return self::FAILURE;
        }

        $exitCode = Artisan::call('migrate', [
            '--database' => 'control',
            '--path' => 'database/migrations/control',
            '--realpath' => false,
            '--force' => true,
        ]);

        $this->output->write(Artisan::output());

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
