<?php

namespace App\Core\Repository;

use App\Core\Repository\BaseRepository;

class TableRepository extends BaseRepository
{

    protected function getTableName(): string
    {
        return 'reservation_table';
    }
}