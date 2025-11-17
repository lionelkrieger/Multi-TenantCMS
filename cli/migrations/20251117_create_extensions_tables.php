<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/includes/bootstrap.php';

use App\Support\Database;

$pdo = Database::connection();

$pdo->exec('
    CREATE TABLE IF NOT EXISTS extensions (
        id CHAR(36) PRIMARY KEY,
        slug VARCHAR(150) UNIQUE,
        name VARCHAR(150),
        version VARCHAR(20),
        description TEXT,
        author VARCHAR(150),
        entry_point VARCHAR(255),
        status ENUM("installed", "active", "inactive", "error"),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
');

echo "Extensions table created successfully.\n";
