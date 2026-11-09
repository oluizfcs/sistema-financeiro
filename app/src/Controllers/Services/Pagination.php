<?php

namespace App\Controllers\Services;

use App\Models\Database;

class Pagination
{
    private String $table;
    private int $maxRowsPerPage;
    private int $lastPage;

    public function __construct(string $table, int $maxRowsPerPage, bool $deleted)
    {
        $this->table = $table;
        $this->maxRowsPerPage = $maxRowsPerPage;

        $this->calculateLastPage($deleted);
    }

    private function calculateLastPage(bool $deleted): void
    {
        if($deleted) {
            $rowCount = Database::countDeleted($this->table, true);
        } else {
            $rowCount = Database::countDeleted($this->table, false);
        }

        $this->lastPage = (int) ($rowCount / $this->maxRowsPerPage);

        if ($rowCount % $this->maxRowsPerPage != 0) {
            $this->lastPage++;
        }
    }

    public function getPage(int $page, string $columns, bool $deleted): array
    {
        return Database::getByPage($this->table, $columns, $this->maxRowsPerPage, $page, $deleted);
    }

    public function getLastPage(): int
    {
        return $this->lastPage;
    }

    public function getMaxRowsPerPage(): int
    {
        return $this->maxRowsPerPage;
    }
}
