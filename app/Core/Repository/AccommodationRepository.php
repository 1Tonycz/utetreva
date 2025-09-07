<?php

namespace App\Core\Repository;

use App\Core\Repository\BaseRepository;

class AccommodationRepository extends BaseRepository
{

    protected function getTableName(): string
    {
        return 'reservations';
    }

    public function getNumberOfNights(\DateTimeInterface|string $from, \DateTimeInterface|string $to): int
    {
        if (!$from instanceof \DateTimeInterface) {
            $from = new \DateTimeImmutable($from);
        }
        if (!$to instanceof \DateTimeInterface) {
            $to = new \DateTimeImmutable($to);
        }

        $interval = $from->diff($to);

        return (int) $interval->days;
    }


}