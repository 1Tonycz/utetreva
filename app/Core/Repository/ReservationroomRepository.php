<?php

declare(strict_types=1);

namespace App\Core\Repository;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

final class ReservationroomRepository
{
    public function __construct(
        private Explorer $database,
    ) {}

    public function isRoomAvailable(
        int $roomId,
        \DateTimeInterface|string $from,
        \DateTimeInterface|string $to,
    ): bool {
        if ($from instanceof \DateTimeInterface) {
            $from = $from->format('Y-m-d');
        }
        if ($to instanceof \DateTimeInterface) {
            $to = $to->format('Y-m-d');
        }

        $row = $this->database->query('
            SELECT NOT EXISTS (
              SELECT 1
              FROM reservation_room rr
              JOIN reservations r ON r.ID = rr.reservation_id
              WHERE rr.room_id = ?
                AND r.Date_from <= ?
                AND r.Date_to   >= ?
            ) AS is_available
        ', $roomId, $to, $from)->fetch();

        return (bool) $row->is_available;
    }

    public function getAvailableRoomIds(
        \DateTimeInterface|string $from,
        \DateTimeInterface|string $to,
    ): array {
        if ($from instanceof \DateTimeInterface) {
            $from = $from->format('Y-m-d');
        }
        if ($to instanceof \DateTimeInterface) {
            $to = $to->format('Y-m-d');
        }

        return $this->database->query('
            SELECT ro.ID
            FROM rooms ro
            WHERE NOT EXISTS (
              SELECT 1
              FROM reservation_room rr
              JOIN reservations r ON r.ID = rr.reservation_id
              WHERE rr.room_id = ro.ID
                AND r.Date_from <= ?
                AND r.Date_to   >= ?
            )
            ORDER BY ro.ID
        ', $to, $from)->fetchPairs(null, 'ID');
    }

    // necháme getAll() opravdu vracet M:N tabulku
    public function getAll(): Selection
    {
        return $this->database->table('reservation_room');
    }

    public function insert(array $data): void
    {
        $this->getAll()->insert($data);
    }

    /**
     * Rezervace pro daný pokoj v časovém rozsahu; Nette „:`-join“ přes M:N tabulku.
     * Vrací pole ActiveRow z tabulky reservations.
     */
    public function getReservationsForRoomInRange(int $roomId, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->database->table('reservations')
            ->where(':reservation_room.room_id', $roomId)   // M:N join přes reservation_room
            ->where('Solved', 1)
            ->where('Old', 0)
            ->where('Date_from < ?', $to)                   // překryv [from, to)
            ->where('Date_to > ?', $from)
            ->fetchAll();
    }

    public function isRoomAvailableExclusive(
        int $roomId,
        \DateTimeInterface|string $from,
        \DateTimeInterface|string $to,
        ?int $excludeReservationId = null
    ): bool {
        if ($from instanceof \DateTimeInterface) {
            $from = $from->format('Y-m-d');
        }
        if ($to instanceof \DateTimeInterface) {
            $to = $to->format('Y-m-d');
        }

        $sql = '
        SELECT NOT EXISTS (
          SELECT 1
          FROM reservation_room rr
          JOIN reservations r ON r.ID = rr.reservation_id
          WHERE rr.room_id = ?
            AND r.Date_from <= ?   -- inkluzivně: blokuje i den odjezdu
            AND r.Date_to   >= ?
    ';

        $params = [$roomId, $to, $from];

        if ($excludeReservationId !== null) {
            $sql .= ' AND r.ID <> ? ';
            $params[] = $excludeReservationId;
        }

        $sql .= ') AS is_available';

        $row = $this->database->query($sql, ...$params)->fetch();
        return (bool) $row->is_available;
    }

    public function deleteByReservationId(int $id): void
    {
        $this->getAll()->where('reservation_id', $id)->delete();
    }

    public function changeRoom(int $reservationId, int $oldRoomId, int $newRoomId): void
    {
        $this->getAll()
            ->where('reservation_id', $reservationId)
            ->where('room_id', $oldRoomId)
            ->update(['room_id' => $newRoomId]);
    }

}
