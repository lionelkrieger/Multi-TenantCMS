<?php

declare(strict_types=1);

require __DIR__ . '/../../app/includes/bootstrap.php';

$connection = Database::connection();

/**
 * @param PDO $connection
 */
function tableExists(PDO $connection, string $table): bool
{
    $statement = $connection->prepare('SHOW TABLES LIKE :table');
    $statement->execute(['table' => $table]);
    return (bool) $statement->fetchColumn();
}

try {
    $connection->beginTransaction();

    if (!tableExists($connection, 'extension_permissions')) {
        $connection->exec(<<<SQL
            CREATE TABLE extension_permissions (
                id VARCHAR(36) PRIMARY KEY NOT NULL,
                extension_id VARCHAR(36) NOT NULL,
                permission VARCHAR(150) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (extension_id) REFERENCES extensions(id) ON DELETE CASCADE,
                UNIQUE KEY unique_extension_permission (extension_id, permission)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    if (!tableExists($connection, 'extension_hooks')) {
        $connection->exec(<<<SQL
            CREATE TABLE extension_hooks (
                id VARCHAR(36) PRIMARY KEY NOT NULL,
                extension_id VARCHAR(36) NOT NULL,
                hook_type ENUM('event','command') NOT NULL,
                hook_name VARCHAR(150) NOT NULL,
                metadata JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (extension_id) REFERENCES extensions(id) ON DELETE CASCADE,
                UNIQUE KEY unique_extension_hook (extension_id, hook_type, hook_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    if (!tableExists($connection, 'extension_routes')) {
        $connection->exec(<<<SQL
            CREATE TABLE extension_routes (
                id VARCHAR(36) PRIMARY KEY NOT NULL,
                extension_id VARCHAR(36) NOT NULL,
                surface ENUM('admin','public','api','webhook') NOT NULL,
                method VARCHAR(10) NOT NULL,
                path VARCHAR(255) NOT NULL,
                capability VARCHAR(150) NULL,
                metadata JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (extension_id) REFERENCES extensions(id) ON DELETE CASCADE,
                UNIQUE KEY unique_extension_route (extension_id, surface, method, path)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    if (!tableExists($connection, 'extension_panels')) {
        $connection->exec(<<<SQL
            CREATE TABLE extension_panels (
                id VARCHAR(36) PRIMARY KEY NOT NULL,
                extension_id VARCHAR(36) NOT NULL,
                panel_key VARCHAR(150) NOT NULL,
                title VARCHAR(150) NOT NULL,
                component VARCHAR(50) NOT NULL,
                schema_path VARCHAR(255) NULL,
                permissions JSON NULL,
                visible_to JSON NULL,
                org_toggle BOOLEAN DEFAULT FALSE,
                sort_order INT DEFAULT 0,
                metadata JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (extension_id) REFERENCES extensions(id) ON DELETE CASCADE,
                UNIQUE KEY unique_extension_panel (extension_id, panel_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    $connection->commit();
    echo "Migration complete: extension manifest metadata tables ensured." . PHP_EOL;
} catch (Throwable $throwable) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    fwrite(STDERR, 'Migration failed: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
