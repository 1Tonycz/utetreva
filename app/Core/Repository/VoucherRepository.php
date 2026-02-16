<?php

namespace App\Core\Repository;

use App\Core\Repository\BaseRepository;

final class VoucherRepository extends BaseRepository
{

    protected function getTableName(): string
    {
        return 'voucher';
    }
}