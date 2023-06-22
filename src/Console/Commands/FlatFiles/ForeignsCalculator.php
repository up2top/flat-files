<?php

namespace up2top\FlatFiles\Console\Commands\FlatFiles;

class ForeignsCalculator
{
    protected $foreigns = [];
    protected $columns;
    protected $records;
    protected $books;
    protected $data;

    /**
     * Create a new instance.
     */
    public function __construct($records, $columns)
    {
        $this->records = $records;
        $this->columns = $columns;
    }

    /**
     * Calculate foreigns for all the records.
     */
    public function calculate()
    {
        $this->calculateParents();
        $this->calculateBooks();
        $this->calculateTranslations();
        $this->calculateSiblings();

        return $this->foreigns;
    }

    /**
     * Calculate parent_id for each data record.
     */
    private function calculateParents()
    {
        foreach ($this->records as $file => $record) {
            $id = $record['id'];

            if (in_array('parent_id', $this->columns)) {
                $parentFile = preg_replace('#[^/]+/(index.(?:([^/]{2})\.)?md)$#', '$1', $file);
                $record['parent_id'] = $this->records[$parentFile]['id'] ?? null;
                $this->foreigns[$id]['parent_id'] = $record['parent_id'];
            }

            $this->data[$id] = $record;
        }
    }

    /**
     * Calculate translation_id for each data record.
     */
    private function calculateTranslations()
    {
        if (! in_array('translation_id', $this->columns)) {
            return;
        }

        foreach ($this->records as $file => $record) {
            $id = $record['id'];
            $filename = basename($file);
            $translationFilename = substr($filename, -6) == '.en.md'
                ? substr($filename, 0, -6) . '.md'
                : substr($filename, 0, -3) . '.en.md';

            $translationFile = str_replace($filename, $translationFilename, $file);
            $this->foreigns[$id]['translation_id'] = $this->records[$translationFile]['id'] ?? null;
        }
    }

    /**
     * Calculate prev_id and next_id for each data record.
     */
    private function calculateSiblings()
    {
        if (! $this->recordsHaveSiblings()) {
            return;
        }

        foreach (array_keys($this->books) as $id) {
            if ($this->notBookRoot($id)) {
                continue;
            }

            $children = $this->getBookChildren($id);

            // Loop through all the pages and update page navigation.
            foreach ($children as $i => $id) {
                $this->foreigns[$id]['prev_id'] = $children[$i - 1] ?? null;
                $this->foreigns[$id]['next_id'] = $children[$i + 1] ?? null;
            }
        }
    }

    /**
     * Convert data to a new books array with children as a subarray.
     */
    private function calculateBooks()
    {
        $this->books = collect($this->data)
            ->filter(function ($value) {
                return ! isset($value['extra'])
                    || strpos($value['extra'], '"unpublished"') === false;
            })
            ->where('parent_id', '!=', null)
            ->groupBy('parent_id')
            ->map(function ($item) {
                return $item->sortByDesc('route')->sortBy('weight')->pluck('id');
            })
            ->toArray();
    }

    /**
     * Recursive function to get a complete list
     * of subpages for a given book including
     * all levels of hierarchy.
     */
    private function getBookChildren($bookId)
    {
        $children = $this->books[$bookId] ?? [];

        // Loop through subpages list.
        foreach ($children as $id) {

            // Extend subpages list reqursively with lower level data.
            if (isset($this->books[$id])) {
                $grandChildren = $this->getBookChildren($id);
                $key = array_search($id, $children);
                array_splice($children, $key+1, 0, $grandChildren);
            }
        }

        return $children;
    }

    /**
     * Check if page is a NOT a book root.
     */
    private function notBookRoot($id)
    {
        $parent_id = $this->data[$id]['parent_id'];
        return $parent_id && !! $this->data[$parent_id]['parent_id'];
    }

    /**
     * Check if siblings calculation is required.
     */
    private function recordsHaveSiblings()
    {
        return in_array('prev_id', $this->columns) && in_array('next_id', $this->columns);
    }
}
