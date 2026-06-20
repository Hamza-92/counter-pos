<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

class DatabaseBackUp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'database:backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $backupDir = storage_path().'/app/public/backup';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        foreach (glob($backupDir.'/*') as $filename) {
            $path = $backupDir.'/'.basename($filename);
            @unlink($path);
        }

        $db_pass = env('DB_PASSWORD');
        $filename = 'backup-'.Carbon::now()->format('Y-m-d').'.sql';
        $outputPath = $backupDir.'/'.$filename;

        if ($db_pass != '') {
            $command = env('DUMP_PATH').' --user='.env('DB_USERNAME')." --password='".$db_pass."' --host=".env('DB_HOST').' '.env('DB_DATABASE').' > '.$outputPath;
        } else {
            $command = env('DUMP_PATH').' --user='.env('DB_USERNAME').' --password='.$db_pass.' --host='.env('DB_HOST').' '.env('DB_DATABASE').' > '.$outputPath;
        }

        $output = [];
        $returnVar = null;
        \exec($command.' 2>&1', $output, $returnVar);

        if ($returnVar !== 0) {
            $err = 'Exit code: '.$returnVar;
            if (!empty($output)) {
                $err .= "\n".implode("\n", $output);
            } elseif (file_exists($outputPath) && filesize($outputPath) > 0) {
                $err .= "\n".trim(@file_get_contents($outputPath));
            }
            $this->line('ERROR_DETAILS: '.$err);
        }

        return $returnVar ?? 0;
    }
}
