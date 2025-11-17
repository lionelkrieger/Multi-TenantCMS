<?php

declare(strict_types=1);

namespace App\Support;

use JsonException;

final class AuditLogger
{
    private string $logPath;

    public function __construct(?string $logPath = null)
    {
        $basePath = $logPath ?? dirname(__DIR__) . '/logs/audit.log';
        $directory = dirname($basePath);
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        $this->logPath = $basePath;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $event, array $context = []): void
    {
        $payload = [
            'timestamp' => date(DATE_ATOM),
            'event' => $event,
            'context' => $context,
        ];

        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            \app_logger()->warning('Failed to encode audit log payload.', [
                'event' => $event,
                'error' => $exception->getMessage(),
            ]);
            return;
        }

        @file_put_contents($this->logPath, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
