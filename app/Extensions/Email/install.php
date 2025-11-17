<?php

declare(strict_types=1);

use App\Extensions\ExtensionContext;

return static function (ExtensionContext $context): void {
    $pdo = $context->connection;

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS email_queue (
            id VARCHAR(36) PRIMARY KEY,
            organization_id VARCHAR(36),
            to_email VARCHAR(255),
            to_name VARCHAR(100),
            subject VARCHAR(255),
            html_body TEXT,
            text_body TEXT,
            status ENUM("pending", "sent", "failed", "retrying") DEFAULT "pending",
            priority INT DEFAULT 10,
            retry_count INT DEFAULT 0,
            scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sent_at TIMESTAMP NULL,
            error_message TEXT,
            FOREIGN KEY (organization_id) REFERENCES organizations(id)
        );
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS documents (
            id VARCHAR(36) PRIMARY KEY,
            organization_id VARCHAR(36),
            entity_type ENUM("reservation", "folio", "membership", "invoice"),
            entity_id VARCHAR(36),
            document_type VARCHAR(50),
            file_path VARCHAR(255),
            title VARCHAR(100),
            generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            downloaded_at TIMESTAMP NULL,
            FOREIGN KEY (organization_id) REFERENCES organizations(id),
            INDEX idx_entity (entity_type, entity_id)
        );
    ');
};
