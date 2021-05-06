<?php

namespace up2top\FlatFiles\Console\Commands\FlatFiles;

use up2top\FlatFiles\Contracts\MessagableContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class FilesManager
{
    protected $command;
    protected $files = [];
    protected $records = [];
    protected $newFiles = [];

    /**
     * Create a new instance.
     *
     * @return void
     */
    public function __construct(MessagableContract $command)
    {
        $this->command = $command;
    }

    /**
     * Read flat files content.
     */
    public function scan($dir, $subdir)
    {
        $this->files = Storage::disk('content')->allFiles($dir);

        // Parent records should come first in order
        // to reference them later by parent_id field.
        usort($this->files, function ($a, $b) {
            return strlen($a) - strlen($b);
        });

        if ($subdir) {
            $subdirs = strpos($subdir, ',') === false
                ? [$subdir] : explode(',', $subdir);

            $subdirs = array_map(function ($subdir) use ($dir) {
                return $dir . '/' . $subdir;
            }, $subdirs);
        }

        foreach ($this->files as $file) {

            if ($subdir && ! in_array(dirname($file), $subdirs)) {
                continue;
            }

            $this->read($file);
        }

        $this->addNewIds($dir);

        return $this->records;
    }

    /**
     * Append id to the new flat files.
     */
    public function updateNewFiles()
    {
        foreach ($this->newFiles as $file) {
            $record = $this->records[$file];
            $this->updateFile($record, $file);
        }
    }

    /**
     * Update single flat file.
     */
    public function updateFile($record, $file)
    {
        // Yaml record data without 'body' value.
        $content = Yaml::dump(
            array_diff_key($record, array_flip(['body']))
        );

        if (isset($record['body']) && $record['body']) {
            $content = "---\n\r" . $content . "---" . $record['body'];
        }

        Storage::disk('content')->put($file, $content);
    }

    /**
     * Count content files.
     */
    public function filesCounter()
    {
        return count($this->files);
    }

    /**
     * Read record from a file.
     */
    private function read($file)
    {
        $contents = Storage::disk('content')-> get($file);

        if (empty($contents)) {
            $this->command->addError('File ' . $file . ' is empty.');
            return;
        }

        $split = preg_split("/---/", $contents, 2, PREG_SPLIT_NO_EMPTY);

        try {
            $record = Yaml::parse($split[0]);
        } catch (ParseException $exception) {
            $this->command->addError(
                'Yaml parse error in file ' . $file . ':' . $exception->getMessage()
            );
            return;
        }

        if (! isset($record['id'])) {
            $this->newFiles[] = $file;
        }

        if (isset($split[1])) {
            $record['body'] = $split[1];
        }

        $this->records[$file] = $record;
    }

    /**
     * Generate ids for the brand new records.
     */
    private function addNewIds($dir)
    {
        $maxInDb = DB::table($dir)->max('id');
        $idsFromFiles = array_column($this->records, 'id');
        $maxInFiles = empty($idsFromFiles) ? 0 : max($idsFromFiles);
        $nextId = max($maxInDb, $maxInFiles) + 1;

        foreach ($this->newFiles as $file) {
            $this->records[$file]['id'] = $nextId++;
        }
    }
}
