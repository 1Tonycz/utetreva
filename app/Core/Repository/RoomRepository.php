<?php

namespace App\Core\Repository;

use App\Core\Repository\BaseRepository;

class RoomRepository extends BaseRepository
{

    protected function getTableName(): string
    {
        return 'rooms';
    }
}