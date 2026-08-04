<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Migrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto:Migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate tables after 1 hour';

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
        $this->error('This destructive legacy command is permanently disabled.');

        return self::FAILURE;
    }
}
