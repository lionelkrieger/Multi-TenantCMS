<?php
// src/Services/DocumentService.php
declare(strict_types=1);

namespace App\Extensions\Email\Services;

use PDO;

class DocumentService
{
    private PDO $pdo;
    private string $storagePath;

    public function __construct(PDO $connection, $storageService)
    {
        $this->pdo = $connection;
        $this->storagePath = $storageService->getPath('documents');
    }

    public function generateReservationInvoice(
        string $organizationId,
        string $reservationId,
        array $reservationData
    ): string {
        // Include TCPDF - your existing library
        require_once __DIR__ . '/../../lib/tcpdf.php';

        // Create new PDF
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document info
        $pdf->SetCreator('SkyLight Hospitality');
        $pdf->SetAuthor('Reservation System');
        $pdf->SetTitle('Reservation Invoice');
        
        // Add page
        $pdf->AddPage();
        
        // Build HTML content
        $html = $this->buildInvoiceHtml($reservationData);
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Generate filename
        $filename = "invoice_{$reservationId}_" . date('Y-m-d') . ".pdf";
        $fullPath = $this->storagePath . "/{$organizationId}/{$filename}";
        
        // Ensure directory exists
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Output PDF
        $pdf->Output($fullPath, 'F');
        
        // Save to database
        $documentId = bin2hex(random_bytes(18));
        $stmt = $this->pdo->prepare(
            'INSERT INTO documents (
                id, organization_id, entity_type, entity_id, document_type, file_path, title, generated_at
            ) VALUES (
                :id, :organization_id, "reservation", :entity_id, "invoice", :file_path, :title, NOW()
            )'
        );
        
        $stmt->execute([
            ':id' => $documentId,
            ':organization_id' => $organizationId,
            ':entity_id' => $reservationId,
            ':file_path' => $fullPath,
            ':title' => "Invoice for Reservation {$reservationId}"
        ]);
        
        return $documentId;
    }

    private function buildInvoiceHtml(array $data): string
    {
        return "
        <h1>Reservation Invoice</h1>
        <p><strong>Guest:</strong> {$data['guest_name']}</p>
        <p><strong>Dates:</strong> {$data['check_in']} to {$data['check_out']}</p>
        <p><strong>Room:</strong> {$data['room_type']}</p>
        <p><strong>Total:</strong> R" . number_format($data['final_amount'], 2) . "</p>
        <p><strong>Payment Method:</strong> {$data['payment_method']}</p>
        <p><strong>Property:</strong> {$data['property_name']}</p>
        <hr>
        <p>This is a computer-generated invoice. No signature required.</p>
        ";
    }

    public function getDocumentPath(string $documentId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT file_path FROM documents WHERE id = ?'
        );
        $stmt->execute([$documentId]);
        $result = $stmt->fetch();
        
        return $result ? $result['file_path'] : null;
    }
}