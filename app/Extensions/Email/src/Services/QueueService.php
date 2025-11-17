<?php
// src/Services/QueueService.php
declare(strict_types=1);

namespace App\Extensions\Email\Services;

use PDO;
use App\Extensions\ExtensionContext;

class QueueService
{
    private PDO $pdo;
    private ExtensionContext $context;

    public function __construct(PDO $connection, ExtensionContext $context)
    {
        $this->pdo = $connection;
        $this->context = $context;
    }

    public function queueEmail(
        string $organizationId,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $attachments = [],
        int $priority = 10
    ): string {
        // Validate inputs
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address');
        }

        // Sanitize
        $subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $htmlBody = htmlspecialchars($htmlBody, ENT_QUOTES, 'UTF-8');
        $textBody = htmlspecialchars($textBody, ENT_QUOTES, 'UTF-8');

        $id = bin2hex(random_bytes(18));

        $stmt = $this->pdo->prepare(
            'INSERT INTO email_queue (
                id, organization_id, to_email, to_name, subject, html_body, text_body, 
                attachments, priority, status, scheduled_at
            ) VALUES (
                :id, :organization_id, :to_email, :to_name, :subject, :html_body, :text_body,
                :attachments, :priority, "pending", NOW()
            )'
        );

        $stmt->execute([
            ':id' => $id,
            ':organization_id' => $organizationId,
            ':to_email' => $toEmail,
            ':to_name' => $toName,
            ':subject' => $subject,
            ':html_body' => $htmlBody,
            ':text_body' => $textBody,
            ':attachments' => json_encode($attachments),
            ':priority' => $priority,
        ]);

        $this->context->logger->info("Email queued for {$toEmail}", ['email_id' => $id]);

        return $id;
    }

    public function processQueue(int $batchSize = 50): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM email_queue 
             WHERE status = "pending" 
             ORDER BY priority DESC, scheduled_at ASC 
             LIMIT ?'
        );
        $stmt->execute([$batchSize]);
        
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($emails as $email) {
            $this->sendEmail($email);
        }
    }

    private function sendEmail(array $emailData): void
    {
        try {
            // Get sender from organization
            $sender = $this->getOrganizationSender($emailData['organization_id']);

            // Prepare headers
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                "From: {$sender}"
            ];

            // Send via PHP mail() - simple, reliable, no dependencies
            $success = mail(
                $emailData['to_email'],
                $emailData['subject'],
                $emailData['html_body'],
                implode("\r\n", $headers)
            );

            if ($success) {
                $this->markAsSent($emailData['id']);
            } else {
                $this->markAsFailed($emailData['id'], 'Mail function failed');
            }
        } catch (\Exception $e) {
            $this->markAsFailed($emailData['id'], $e->getMessage());
        }
    }

    private function markAsSent(string $emailId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE email_queue SET status = "sent", sent_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$emailId]);
    }

    private function markAsFailed(string $emailId, string $error): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE email_queue SET status = "failed", error_message = ? WHERE id = ?'
        );
        $stmt->execute([$error, $emailId]);
    }

    private function getOrganizationSender(string $orgId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT email_from_address FROM organizations WHERE id = ?'
        );
        $stmt->execute([$orgId]);
        $org = $stmt->fetch();
        
        return $org['email_from_address'] ?? 'no-reply@skylinthospitality.com';
    }

    public function queueReservationConfirmation(
        string $organizationId,
        string $guestEmail,
        array $reservationData
    ): void {
        // Build reservation confirmation email
        $subject = "Your Booking Confirmation - {$reservationData['room_type']}";
        
        $htmlBody = "
        <h2>Thank you for your booking!</h2>
        <p>Dear {$reservationData['guest_name']},</p>
        <p>Your reservation has been confirmed:</p>
        <ul>
            <li><strong>Check-in:</strong> {$reservationData['check_in']}</li>
            <li><strong>Check-out:</strong> {$reservationData['check_out']}</li>
            <li><strong>Room:</strong> {$reservationData['room_type']}</li>
            <li><strong>Total:</strong> R" . number_format($reservationData['final_amount'], 2) . "</li>
        </ul>
        <p>We look forward to welcoming you!</p>
        <p>Best regards,<br>{$reservationData['property_name']}</p>
        ";

        $textBody = "Thank you for your booking. Check-in: {$reservationData['check_in']}, Check-out: {$reservationData['check_out']}, Room: {$reservationData['room_type']}, Total: R" . number_format($reservationData['final_amount'], 2);

        $this->queueEmail(
            $organizationId,
            $guestEmail,
            $reservationData['guest_name'],
            $subject,
            $htmlBody,
            $textBody,
            [],
            20 // High priority for confirmations
        );
    }
}