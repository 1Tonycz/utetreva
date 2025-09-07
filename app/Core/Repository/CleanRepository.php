<?php

namespace App\Core\Repository;

use App\Core\Repository\BaseRepository;

class CleanRepository extends BaseRepository
{

    protected function getTableName(): string
    {
        return 'room_cleaning';
    }
}