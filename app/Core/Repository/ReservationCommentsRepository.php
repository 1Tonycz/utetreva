<?php

namespace App\Core\Repository;

use App\Core\Repository\BaseRepository;

class ReservationCommentsRepository extends BaseRepository
{

    protected function getTableName(): string
    {
        return 'reservation_comments';
    }
}