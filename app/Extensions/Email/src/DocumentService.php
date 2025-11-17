<?php

declare(strict_types=1);

namespace App\Extensions\Email;

use App\Support\Database;
use PDO;

class DocumentService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function generatePlaceholderPdf(
        string $organizationId,
        string $entityType,
        string $entityId,
        string $documentType,
        string $title
    ): string {
        $id = uniqid('doc_', true);
        $storagePath = __DIR__ . "/../../../../storage/extensions/email/{$organizationId}/documents";
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        $filePath = "{$storagePath}/{$id}.pdf";

        // Create a placeholder PDF
        file_put_contents($filePath, "This is a placeholder PDF for {$title}.");

        $stmt = $this->pdo->prepare(
            'INSERT INTO documents (id, organization_id, entity_type, entity_id, document_type, file_path, title)
             VALUES (:id, :organization_id, :entity_type, :entity_id, :document_type, :file_path, :title)'
        );

        $stmt->execute([
            ':id' => $id,
            ':organization_id' => $organizationId,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':document_type' => $documentType,
            ':file_path' => $filePath,
            ':title' => $title,
        ]);

        return $id;
    }
}
