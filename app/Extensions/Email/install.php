<?php
// install.php
declare(strict_types=1);

use App\Extensions\ExtensionContext;

return static function (ExtensionContext $context): void {
    $pdo = $context->connection;

    // Create email queue table
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS email_queue (
            id VARCHAR(36) PRIMARY KEY,
            organization_id VARCHAR(36) NOT NULL,
            to_email VARCHAR(255) NOT NULL,
            to_name VARCHAR(100),
            subject VARCHAR(255) NOT NULL,
            html_body LONGTEXT,
            text_body TEXT,
            attachments JSON,
            status ENUM("pending", "sent", "failed", "retrying") DEFAULT "pending",
            priority INT DEFAULT 10,
            retry_count INT DEFAULT 0,
            scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sent_at TIMESTAMP NULL,
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            INDEX idx_org_status (organization_id, status),
            INDEX idx_priority (priority, scheduled_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ');

    // Create documents table
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS documents (
            id VARCHAR(36) PRIMARY KEY,
            organization_id VARCHAR(36) NOT NULL,
            entity_type ENUM("reservation", "folio", "membership", "invoice") NOT NULL,
            entity_id VARCHAR(36) NOT NULL,
            document_type VARCHAR(50) NOT NULL,
            file_path VARCHAR(512) NOT NULL,
            title VARCHAR(255) NOT NULL,
            generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            downloaded_at TIMESTAMP NULL,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_org_type (organization_id, document_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ');

    $context->logger->info('Email extension tables created.');
};