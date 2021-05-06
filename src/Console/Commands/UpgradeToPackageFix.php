<?php

namespace up2top\FlatFiles\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class UpgradeToPackageFix extends Command
{
    protected $signature = 'flat:upgrade-to-package-fix';
    protected $description = 'Replace existing non-package flatfiles migration with the package migration.';

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
        $migrations = DB::table('migrations');
        $maxId = $migrations->max('id');
        $maxBatch = $migrations->max('batch');

        $oldMigration = $migrations->where('migration', 'LIKE', '%flatfiles%')->first();

        if (! $oldMigration) {
            return;
        }

        if ($oldMigration->id == $maxId || $oldMigration->batch == $maxBatch) {
            return;
        }

        $migrationFile = base_path('database/migrations/' . $oldMigration->migration) . '.php';

        if (! File::exists($migrationFile)) {
            return;
        }

        File::delete($migrationFile);

        $migrations->where('migration', 'LIKE', '%flatfiles%')->update([
            'id' => $maxId + 1,
            'migration' => '2021_05_06_000000_create_flatfiles_table',
            'batch' => $maxBatch + 1
        ]);

        $this->info('Fix applied successfully.');
    }
}
