<?php

namespace up2top\FlatFiles\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use up2top\FlatFiles\Console\Commands\FlatFiles\FlatFilesLoader;
use up2top\FlatFiles\Console\Messagable;
use up2top\FlatFiles\Contracts\MessagableContract;

class LoadFlatContent extends Command implements MessagableContract
{
    use Messagable;

    protected $signature = 'flat:load-content {dir?} {--dir=} {--subdir=}';
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
        $dir = $this->argument('dir') ?? $this->option('dir');

        $subdir = $this->option('subdir') ?? null;

        $dirs = Storage::disk('content')->directories();

        if (empty($dirs)) {
            $this->error('No subfolders in content directory.');
            return;
        }

        if (! in_array($dir, $dirs)) {
            $dir = $this->choice('Which content type to load?', $dirs, 0);
        }

        $flatFilesLoader = new FlatFilesLoader($this, $dir, $subdir);

        $flatFilesLoader->checkFiles();

        $this->showMessages();

        if ($this->isStopRequired()) {
            $this->comment(ucfirst($dir) . ' loading interrupted.');
            return;
        }

        $flatFilesLoader->loadFiles();

        $this->comment(ucfirst($dir) . ' loaded successfully.');
    }
}
