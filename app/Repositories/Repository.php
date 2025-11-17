<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\RepositoryException;
use PDO;
use PDOException;
use PDOStatement;

abstract class Repository
{
    public function __construct(protected PDO $connection)
    {
    }

    protected function execute(string $sql, array $params = []): PDOStatement
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute($params);
            return $statement;
        } catch (PDOException $exception) {
            throw new RepositoryException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }
    }

    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $statement = $this->execute($sql, $params);
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->execute($sql, $params);
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $results === false ? [] : $results;
    }

    protected function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        $statement = $this->execute($sql, $params);
        $value = $statement->fetchColumn($column);
        return $value === false ? null : $value;
    }

    protected function insert(string $sql, array $params = []): bool
    {
        $this->execute($sql, $params);
        return true;
    }

    protected function update(string $sql, array $params = []): int
    {
        $statement = $this->execute($sql, $params);
        return $statement->rowCount();
    }

    protected function delete(string $sql, array $params = []): int
    {
        $statement = $this->execute($sql, $params);
        return $statement->rowCount();
    }
}
