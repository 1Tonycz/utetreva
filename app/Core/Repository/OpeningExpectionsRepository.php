<?php

namespace App\Core\Repository;

use App\Core\Repository\BaseRepository;

final class OpeningExpectionsRepository extends BaseRepository
{

    protected function getTableName(): string
    {
        return 'opening_exceptions';
    }
}