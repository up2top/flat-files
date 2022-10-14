<?php

namespace up2top\FlatFiles\Console\Commands\FlatFiles;

use up2top\FlatFiles\Contracts\MessagableContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Parsedown;

class DataConvertor
{
    protected $dir;
    protected $command;
    protected $columns = [];
    protected $slugRoutes = [];

    /**
     * Create a new instance.
     *
     * @return void
     */
    public function __construct(MessagableContract $command, $dir)
    {
        $this->dir = $dir;
        $this->command = $command;
        $this->getDatabaseColumns();
    }

    /**
     * Convert records to the database format.
     */
    public function run($records)
    {
        $filesData = [];

        $defaultLocale = app()->getLocale();

        foreach ($records as $file => $record) {
            $id = $record['id'];

            // Dublicate id in different files.
            if (isset($filesData[$id])) {
                $this->command->addError('Dublicate id ' . $id . ' in file ' . $file . '.');
                continue;
            }

            $data = [];

            $route = $this->getFileRoute($file, $record);

            foreach ($this->columns as $column => $length) {
                switch ($column) {
                case 'route':
                    $data[$column] = $route;
                    break;

                case 'slug':
                    $data[$column] = $record['slug'] ?? basename($route);
                    break;

                case 'depth':
                    $data[$column] = substr_count($file, '/') - 1;
                    break;

                case 'extra':
                    $data[$column] = json_encode(
                        array_diff_key($record, array_flip(array_keys($this->columns)))
                    );
                    break;

                case 'weight':
                    $data[$column] = $record[$column] ?? 0;
                    break;

                case 'body':
                    $data[$column] = isset($record[$column]) && $record[$column]
                        ? Parsedown::instance()->text($record[$column]) : null;
                    break;

                case 'locale':
                    $parts = explode('.', basename($file));
                    $data[$column] = sizeof($parts) == 3 && strlen($parts[0]) == 2
                        ? $parts[0] : $defaultLocale;
                    break;

                default:
                    $data[$column] = $record[$column] ?? null;
                }

                // Check length of a given value.
                if ($this->isValueTooLong($data[$column], $length)) {
                    $this->command->addError('Field ' . $column . ' is too long in file ' . $file . '.');
                }
            }

            $filesData[$id] = $data;
        }

        return $filesData;
    }

    /**
     * Columns getter.
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Get route from filename and path.
     */
    private function getFileRoute($file, $record)
    {
        $path = substr($file, strpos($file, '/') + 1);
        $route = substr($path, 0, strrpos($path, '/'));

        if (basename($file) != 'index.md' && strlen($route) > 0) {
            $parentRoute = substr($route, 0, strrpos($route, '/'));
            $slugRoute = $record['slug'] ?? basename($route);

            if (strlen($parentRoute) > 0) {
                $parentSlug = $this->slugRoutes[$parentRoute] ?? $parentRoute;
                $slugRoute = $parentSlug . '/' . $slugRoute;
            }

            $this->slugRoutes[$route] = $slugRoute;
            $route = $slugRoute;
        }

        return $route;
    }

    /**
     * Get listing of columns with maximum length.
     */
    private function getDatabaseColumns()
    {
        foreach (Schema::getColumnListing($this->dir) as $column) {
            $this->columns[$column] = DB::connection()
                ->getDoctrineColumn($this->dir, $column)
                ->getLength();
        }
    }

    /**
     * Check if value is longer than a given length.
     */
    private function isValueTooLong($value, $length)
    {
        return $length > 0 && mb_strlen($value) > $length;
    }
}
