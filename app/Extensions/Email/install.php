<?php

declare(strict_types=1);

use App\Extensions\ExtensionContext;

return static function (ExtensionContext $context): void {
    $pdo = $context->connection;

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS email_queue (
            id CHAR(36) PRIMARY KEY,
            organization_id CHAR(36) NOT NULL,
            recipient VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            status ENUM("pending", "sent", "failed") DEFAULT "pending",
            attempts INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ');
};
