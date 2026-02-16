<?php

namespace App\Core\Repository;

use App\Core\Repository\BaseRepository;


class EventRepository extends BaseRepository
{

    protected function getTableName(): string
    {
        return 'event';
    }
}