<?php

declare(strict_types=1);

use PDO;
use RuntimeException;
use Throwable;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $configPath = config_path('database.php');
            if (!file_exists($configPath)) {
                throw new RuntimeException('Database config not generated. Run installer.');
            }

            $config = require $configPath;
            self::$connection = new PDO(
                sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['host'], $config['database'], $config['charset']),
                $config['username'],
                $config['password'],
                $config['options'] ?? []
            );
        }

        return self::$connection;
    }

    public static function setConnection(PDO $connection): void
    {
        self::$connection = $connection;
    }

    public static function disconnect(): void
    {
        self::$connection = null;
    }

    public static function transaction(callable $callback): mixed
    {
        $connection = self::connection();
        $connection->beginTransaction();

        try {
            $result = $callback($connection);
            $connection->commit();
            return $result;
        } catch (Throwable $throwable) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $throwable;
        }
    }
}
