<?php

namespace up2top\FlatFiles\Console\Commands\FlatFiles;

use up2top\FlatFiles\Contracts\MessagableContract;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FlatFilesLoader
{
    protected $command;
    protected $dir;
    protected $contentType;
    protected $filesManager;
    protected $dataConvertor;
    protected $siblings = [];
    protected $translations = [];
    protected $dbData = [];
    protected $filesData = [];
    protected $insertData = [];
    protected $updateData = [];
    protected $deleteData = [];
    protected $hashData = [];
    protected $subdir;

    /**
     * Create a new instance.
     */
    public function __construct(MessagableContract $command, $dir, $subdir)
    {
        $this->command = $command;
        $this->dir = $dir;
        $this->subdir = $subdir;
        $this->contentType = Str::singular($dir);
        $this->filesManager = new FilesManager($command);
        $this->dataConvertor = new DataConvertor($this->command, $this->dir);
    }

    /**
     * Read and check database and flat files content.
     */
    public function checkFiles()
    {
        $this->loadDatabaseData();
        $this->prepareData();
    }

    /**
     * Write flat files content to the database.
     */
    public function loadFiles()
    {
        $this->sortData();
        $this->updateDatabase();
        $this->filesManager->updateNewFiles();
        $this->outputResults();
    }

    /**
     * Load required database data.
     */
    private function loadDatabaseData()
    {
        // Check if content table exists in the database.
        if (! Schema::hasTable($this->dir)) {
            $this->command->addError('Table "' . $this->dir . '" is missing in the database.');
        }

        // Load list of existing database content.
        try {
            $this->dbData = DB::table('flatfiles')
                ->where('flattable_type', $this->contentType)
                ->pluck('hash', 'flattable_id')
                ->toArray();
        } catch (QueryException $exception) {
            $this->command->addError(
                'Error on reading ' . $this->contentType . ' records from database table "flatfiles": ' . $exception->getMessage()
            );
        }
    }

    /**
     * Prepare flat content for writing to the database.
     */
    private function prepareData()
    {
        $records = $this->filesManager->scan($this->dir, $this->subdir);
        $this->filesData = $this->dataConvertor->run($records);
        $this->setTraslations($records);
        $this->siblings = (new SiblingsCalculator($this->filesData))->calculate();
    }

    /**
     * Set translations.
     */
    private function setTraslations($records)
    {
        foreach ($records as $file => $record) {
            $id = $record['id'];
            $filename = basename($file);
            $translationFilename = $filename == 'index.md' ? 'en.index.md' : 'index.md';
            $translationFile = str_replace($filename, $translationFilename, $file);

            if (array_key_exists('translation_id', $this->filesData[$id])
                && isset($records[$translationFile]['id'])) {
                $this->translations[$id] = [
                    'translation_id' => $records[$translationFile]['id']
                ];
            }
        }
    }

    /**
     * Divide files data as for update or insert.
     */
    private function sortData()
    {
        foreach ($this->filesData as $id => $data) {
            $this->hashData[$id] = [
                'flattable_id' => $id,
                'flattable_type' => $this->contentType,
                'hash' => md5(json_encode(
                    array_merge(
                        $data,
                        $this->siblings[$id] ?? [],
                        $this->translations[$id] ?? []
                    )
                ))
            ];

            if ($this->contentUnchanged($id)) {
                continue;
            }

            if ($this->recordExistsInDatabase($id)) {
                $this->updateData[$id] = $data;
            } else {
                $this->insertData[$id] = $data;
            }
        }
    }

    /**
     * Write content to the database.
     */
    private function updateDatabase()
    {
        $this->resetForeignFields();

        $this->deleteContent();

        DB::table($this->dir)->insert(array_values($this->insertData));

        foreach ($this->updateData as $id => $data) {
            DB::table($this->dir)->where('id', $id)->update($data);
        }

        $this->updateSiblings();

        $this->updateTranslations();

        DB::table('flatfiles')->where('flattable_type', $this->contentType)->delete();
        DB::table('flatfiles')->insert(array_values($this->hashData));
    }

    /**
     * Print results to the console.
     */
    private function outputResults()
    {
        $results = [
            'scanned' => $this->filesManager->filesCounter(),
            'created' => count($this->insertData),
            'updated' => count($this->updateData),
            'deleted' => count($this->deleteData)
        ];

        foreach ($results as $type => $count) {
            $this->command->info(
                $count . ($count == 1 ? ' item ' : ' items ') . $type . '.'
            );
        }
    }

    /**
     * Reset foreign fields to NULL to avoid error on deleting records:
     * General error: 3008 Foreign key cascade delete/update exceeds max depth of 15.
     */
    private function resetForeignFields()
    {
        $foreigns = [
            'translation_id',
            'parent_id',
            'prev_id',
            'next_id'
        ];

        $columns = $this->dataConvertor->getColumns();
        $columns = array_intersect($foreigns, array_keys($columns));

        if (! empty($columns)) {
            $data = array_combine($columns, array_fill(0, count($columns), null));
            DB::table($this->dir)->update($data);
        }
    }

    /**
     * Update content prev_id and next_id values.
     */
    private function updateSiblings()
    {
        $keys = array_keys($this->insertData + $this->updateData);
        $siblings = array_intersect_key($this->siblings, array_flip($keys));

        foreach ($siblings as $id => $data) {
            DB::table($this->dir)->where('id', $id)->update($data);
        }
    }

    /**
     * Update content translation_id value.
     */
    private function updateTranslations()
    {
        $keys = array_keys($this->insertData + $this->updateData);
        $translations = array_intersect_key($this->translations, array_flip($keys));

        foreach ($translations as $id => $data) {
            DB::table($this->dir)->where('id', $id)->update($data);
        }
    }

    /**
     * Delete unnecessary content records from database.
     */
    private function deleteContent()
    {
        $this->deleteData = array_diff(
            array_keys($this->dbData),
            array_keys($this->filesData)
        );

        DB::table($this->dir)->whereIn('id', $this->deleteData)->delete();
    }

    /**
     * Check if content is already loaded in the database.
     */
    private function contentUnchanged($id)
    {
        return isset($this->dbData[$id]) && $this->hashData[$id]['hash'] == $this->dbData[$id];
    }

    /**
     * Is an existing record.
     */
    private function recordExistsInDatabase($id)
    {
        return in_array($id, array_keys($this->dbData));
    }
}
