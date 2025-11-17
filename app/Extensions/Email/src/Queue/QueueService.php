<?php

declare(strict_types=1);

namespace App\Extensions\Email\Queue;

use App\Support\Database;
use PDO;

class QueueService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function queueEmail(
        string $organizationId,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody,
        int $priority = 10
    ): void {
        $id = uniqid('email_', true);

        $stmt = $this->pdo->prepare(
            'INSERT INTO email_queue (id, organization_id, to_email, to_name, subject, html_body, text_body, priority)
             VALUES (:id, :organization_id, :to_email, :to_name, :subject, :html_body, :text_body, :priority)'
        );

        $stmt->execute([
            ':id' => $id,
            ':organization_id' => $organizationId,
            ':to_email' => $toEmail,
            ':to_name' => $toName,
            ':subject' => $subject,
            ':html_body' => $htmlBody,
            ':text_body' => $textBody,
            ':priority' => $priority,
        ]);
    }
}
