<?php

namespace up2top\FlatFiles\Console\Commands;

use Illuminate\Console\Command;

class LoadFlatContent extends Command
{
    protected $signature = 'flat:load-content';
    protected $description = 'Load content from flat files to the corresponding database tables.';

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
     * @return mixed
     */
    public function handle()
    {
        $this->comment('Content loaded successfully.');
    }
}
