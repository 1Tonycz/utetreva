<?php

declare(strict_types=1);

namespace App\Core\Repository;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

abstract class BaseRepository
{
    public function __construct(
        private readonly Explorer $database
    ){}

    abstract protected function getTableName(): string;

    public function getAll(): Selection
    {
        return $this->database->table($this->getTableName());
    }

    public function getById(string|int $id): ?ActiveRow
    {
        return $this->getAll()->get($id);
    }

    public function getBy(array|string $cond, ...$params): Selection
    {
        return $this->getAll()->where($cond, $params);
    }

    public function insert(array $data): void
    {
        $this->getAll()->insert($data);
    }

    public function delete(string|int $id): void
    {
        $this->getAll()
            ->get($id)
            ->delete();
    }

    public function update(string|int $id, array $data): void
    {
        $this->getAll()
            ->get($id)
            ->update($data);
    }
}