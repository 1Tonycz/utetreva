<?php

namespace App\Core\Repository;

use App\Core\Repository\BaseRepository;

class VoucherAmountHistoryRepository extends BaseRepository
{

    protected function getTableName(): string
    {
        return 'voucher_amount_history';
    }
}