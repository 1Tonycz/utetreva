<?php

namespace App\Core\Repository;

use App\Core\Repository\BaseRepository;

class GalerieRepository extends BaseRepository
{

    protected function getTableName(): string
    {
        return "galleries";
    }
}