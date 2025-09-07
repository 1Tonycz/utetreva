<?php

namespace App\Core\Repository;

use App\Core\Repository\BaseRepository;

class FoodRepository extends BaseRepository
{

    protected function getTableName(): string
    {
        return 'food';
    }
}