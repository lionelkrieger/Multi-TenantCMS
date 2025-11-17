<?php

declare(strict_types=1);

namespace App\Support;

final class Logger
{
    public function __construct(private readonly string $logPath)
    {
        $directory = dirname($logPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $entry = sprintf('[%s] %s %s %s%s',
            date('c'),
            $level,
            $message,
            json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            PHP_EOL
        );

        file_put_contents($this->logPath, $entry, FILE_APPEND | LOCK_EX);
    }
}
