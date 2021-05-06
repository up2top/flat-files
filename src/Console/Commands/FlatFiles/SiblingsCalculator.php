<?php

namespace up2top\FlatFiles\Console\Commands\FlatFiles;

class SiblingsCalculator
{
    protected $data;
    protected $books;

    /**
     * Create a new instance.
     */
    public function __construct($data)
    {
        $this->data = $data;
        $this->books = $this->getBooks();
    }

    /**
     * Calculate prev_id and next_id for each data record.
     */
    public function calculate()
    {
        $siblings = [];

        foreach (array_keys($this->books) as $id) {
            if ($this->notBookRoot($id)) {
                continue;
            }

            $children = $this->getBookChildren($id);

            // Loop through all the pages and update page navigation.
            foreach ($children as $i => $id) {
                $siblings[$id] = [
                    'prev_id' => $children[$i - 1] ?? null,
                    'next_id' => $children[$i + 1] ?? null
                ];
            }
        }

        return $siblings;
    }

    /**
     * Convert data to a new books array with children as a subarray.
     */
    private function getBooks()
    {
        return collect($this->data)
            ->filter(function ($value) {
                return ! isset($value['flat'])
                    || strpos($value['flat'], '"unpublished"') === false;
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
}
