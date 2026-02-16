<?php

namespace App\Core\Repository;

use App\Core\Repository\BaseRepository;

class TableCommentsRepository extends BaseRepository
{

    protected function getTableName(): string
    {
        return 'table_comments';
    }
}