<?php

declare(strict_types=1);

require __DIR__ . '/../app/includes/bootstrap.php';

use App\Extensions\ExtensionRegistry;
use App\Support\Database;

$usage = static function (): void {
    echo "Usage: php cli/extensions.php <command> [options]\n";
    echo "Commands:\n";
    echo "  sync                Discover extension manifests and upsert metadata.\n";
    echo "  email:work          Run the email queue worker.\n";
};

$command = $argv[1] ?? null;
if ($command === null) {
    $usage();
    exit(1);
}

try {
    $registry = new ExtensionRegistry();

    switch ($command) {
        case 'sync':
            $registry->discover();
            echo "Extensions synced successfully." . PHP_EOL;
            break;

        case 'email:work':
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
                        // Simulate sending email by logging to a file
                        $logPath = __DIR__ . "/../../storage/logs";
                        if (!is_dir($logPath)) {
                            mkdir($logPath, 0755, true);
                        }
                        $logFile = "{$logPath}/sent_emails.log";
                        $logEntry = date('Y-m-d H:i:s') . " --- TO:{$job['to_email']} SUBJECT:{$job['subject']}" . PHP_EOL;
                        file_put_contents($logFile, $logEntry, FILE_APPEND);
                        echo "Email to {$job['to_email']} logged.\n";

                        // Mark as sent
                        $updateStmt = $pdo->prepare('UPDATE email_queue SET status = "sent", sent_at = NOW() WHERE id = :id');
                        $updateStmt->execute([':id' => $job['id']]);
                        echo "Email sent.\n";

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
            break;

        default:
            echo "Unknown command: {$command}" . PHP_EOL;
            $usage();
            exit(1);
    }
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Error: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
