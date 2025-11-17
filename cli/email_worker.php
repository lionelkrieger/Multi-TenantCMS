<?php

declare(strict_types=1);

require __DIR__ . '/../app/includes/bootstrap.php';

use App\Support\Database;

$pdo = Database::connection();
echo "Email worker started. Waiting for jobs...\n";

while (true) {
    $stmt = $pdo->prepare(
        'SELECT * FROM email_queue
         WHERE status IN ("pending", "retrying")
           AND scheduled_at <= NOW()
         ORDER BY priority ASC, scheduled_at ASC
         LIMIT 10'
    );
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($jobs)) {
        sleep(10);
        continue;
    }

    foreach ($jobs as $job) {
        try {
            // Use PHP's mail() function to send the email
            $to = "{$job['to_name']} <{$job['to_email']}>";
            $subject = $job['subject'];
            $message = $job['text_body'];
            $headers = 'From: noreply@example.com' . "\r\n" .
                'Reply-To: noreply@example.com' . "\r\n" .
                'X-Mailer: PHP/' . phpversion();

            if (mail($to, $subject, $message, $headers)) {
                echo "Email sent to {$job['to_email']}.\n";
                // Mark as sent
                $updateStmt = $pdo->prepare('UPDATE email_queue SET status = "sent", sent_at = NOW() WHERE id = :id');
                $updateStmt->execute([':id' => $job['id']]);
            } else {
                throw new Exception("Failed to send email.");
            }

        } catch (Throwable $e) {
            $retryCount = (int)$job['retry_count'] + 1;
            if ($retryCount > 3) {
                // Mark as failed
                $updateStmt = $pdo->prepare('UPDATE email_queue SET status = "failed", error_message = :error WHERE id = :id');
                $updateStmt->execute([':id' => $job['id'], ':error' => $e->getMessage()]);
                fwrite(STDERR, "Email failed permanently: " . $e->getMessage() . PHP_EOL);
            } else {
                // Schedule retry with exponential backoff
                $delay = pow(5, $retryCount); // 5s, 25s, 125s
                $scheduledAt = date('Y-m-d H:i:s', time() + $delay);
                $updateStmt = $pdo->prepare(
                    'UPDATE email_queue
                     SET status = "retrying", retry_count = :retry_count, scheduled_at = :scheduled_at, error_message = :error
                     WHERE id = :id'
                );
                $updateStmt->execute([
                    ':id' => $job['id'],
                    ':retry_count' => $retryCount,
                    ':scheduled_at' => $scheduledAt,
                    ':error' => $e->getMessage(),
                ]);
                echo "Email failed, retrying in {$delay} seconds...\n";
            }
        }
    }
}
