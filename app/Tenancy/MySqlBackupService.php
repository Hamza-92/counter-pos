<?php

namespace App\Tenancy;

use App\Models\ControlPlane\Tenant;
use App\Models\ControlPlane\TenantBackup;
use App\Models\ControlPlane\TenantDatabase;
use RuntimeException;
use Symfony\Component\Process\Process;

final class MySqlBackupService
{
    public function __construct(private readonly PortableMySqlBackup $portable)
    {
    }

    public function create(Tenant $tenant, TenantDatabase $database): TenantBackup
    {
        $directory = storage_path('app/tenants/'.$tenant->id.'/private/backups');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the tenant backup directory.');
        }

        $filename = now()->format('Ymd_His').'_'.bin2hex(random_bytes(5)).'.sql';
        $target = $directory.DIRECTORY_SEPARATOR.$filename;
        if ($this->usesPortableDriver()) {
            $this->portable->dump($target, $database->database_name);
        } else {
            $defaults = $this->temporaryDefaultsFile($database, $directory);
            try {
                $process = new Process([
                    $this->binary('mysqldump'), '--defaults-extra-file='.$defaults,
                    '--single-transaction', '--quick', '--routines', '--triggers', '--events',
                    '--result-file='.$target, $database->database_name,
                ]);
                $process->setTimeout(1800);
                $process->mustRun();
            } finally {
                if (isset($defaults) && is_file($defaults)) {
                    @unlink($defaults);
                }
            }
        }

        if (! is_file($target) || filesize($target) === 0) {
            throw new RuntimeException('The database backup was not created.');
        }

        return TenantBackup::query()->create([
            'tenant_id' => $tenant->id,
            'database_name' => $database->database_name,
            'relative_path' => $filename,
            'sha256' => hash_file('sha256', $target),
            'size_bytes' => filesize($target),
            'status' => 'verified',
            'created_by' => auth('control')->id(),
        ]);
    }

    public function restore(Tenant $tenant, TenantDatabase $database, TenantBackup $backup): void
    {
        if ($backup->tenant_id !== $tenant->id || $backup->database_name !== $database->database_name || $backup->status !== 'verified') {
            throw new RuntimeException('The backup does not match the selected tenant database.');
        }

        $path = storage_path('app/tenants/'.$tenant->id.'/private/backups/'.$backup->relative_path);
        if (! is_file($path) || ! hash_equals($backup->sha256, hash_file('sha256', $path))) {
            throw new RuntimeException('Backup integrity verification failed.');
        }

        if ($this->usesPortableDriver()) {
            $this->portable->restore($path, $database->database_name);
        } else {
            $directory = dirname($path);
            $defaults = $this->temporaryDefaultsFile($database, $directory);
            $input = fopen($path, 'rb');
            if ($input === false) {
                @unlink($defaults);
                throw new RuntimeException('Unable to read the verified backup.');
            }
            try {
                $process = new Process([
                    $this->binary('mysql'), '--defaults-extra-file='.$defaults, $database->database_name,
                ]);
                $process->setInput($input);
                $process->setTimeout(1800);
                $process->mustRun();
            } finally {
                fclose($input);
                if (isset($defaults) && is_file($defaults)) {
                    @unlink($defaults);
                }
            }
        }
    }

    private function usesPortableDriver(): bool
    {
        $driver = strtolower((string) config('tenancy.backup_driver', 'auto'));
        if (! in_array($driver, ['auto', 'process', 'php'], true)) {
            throw new RuntimeException('TENANT_BACKUP_DRIVER must be auto, process, or php.');
        }

        return $driver === 'php' || ($driver === 'auto' && ! function_exists('proc_open'));
    }

    private function temporaryDefaultsFile(TenantDatabase $database, string $directory): string
    {
        $path = $directory.DIRECTORY_SEPARATOR.'.mysql_'.bin2hex(random_bytes(8)).'.cnf';
        $username = $database->migration_username ?: $database->username;
        $password = $database->migration_username ? $database->migration_password : $database->password;
        $content = "[client]\nhost={$database->host}\nport={$database->port}\nuser={$username}\npassword=".$this->quoteOption((string) $password)."\n";
        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new RuntimeException('Unable to create a protected database client configuration.');
        }
        @chmod($path, 0600);

        return $path;
    }

    private function quoteOption(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function binary(string $name): string
    {
        $configured = env(strtoupper($name).'_BINARY');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $xampp = 'C:\\xampp\\mysql\\bin\\'.$name.'.exe';

        return is_file($xampp) ? $xampp : $name;
    }
}
