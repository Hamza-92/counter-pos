<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TenantImportFiles extends Command
{
    protected $signature = 'tenant:files-import {--tenant= : Tenant UUID}';
    protected $description = 'Copy a staged customer public directory into its isolated tenant file root';

    public function handle(): int
    {
        $tenantId = (string) $this->option('tenant');
        if (! Str::isUuid($tenantId)) {
            $this->error('Tenant must be supplied as a UUID.');

            return self::FAILURE;
        }

        $source = storage_path('app/imports/'.$tenantId.'/public');
        $target = storage_path('app/public/tenants/'.$tenantId);
        if (! is_dir($source)) {
            $this->error('Stage files at storage/app/imports/'.$tenantId.'/public first.');

            return self::FAILURE;
        }

        $blocked = '/\.(php\d*|phtml|phar|cgi|pl|sh|exe|com|bat|cmd|htaccess)$/i';
        $copied = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isLink() || ! $file->isFile()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
            if (str_contains($relative, '..') || preg_match($blocked, $relative)) {
                $this->error('Unsafe staged file detected: '.$relative);

                return self::FAILURE;
            }
            $destination = $target.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (! is_dir(dirname($destination)) && ! mkdir(dirname($destination), 0750, true) && ! is_dir(dirname($destination))) {
                $this->error('Failed to create the destination directory.');

                return self::FAILURE;
            }
            if (! copy($file->getPathname(), $destination)) {
                $this->error('Failed to copy '.$relative);

                return self::FAILURE;
            }
            $copied++;
        }

        $this->info($copied.' files copied. The staging directory was retained for manual verification/removal.');

        return self::SUCCESS;
    }
}
