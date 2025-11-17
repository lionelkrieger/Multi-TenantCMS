<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

final class ErrorHandler
{
    public function __construct(private readonly Logger $logger)
    {
    }

    public function register(): void
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
    }

    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        $this->logger->error('PHP Error', compact('severity', 'message', 'file', 'line'));
        return false;
    }

    public function handleException(Throwable $exception): void
    {
        $this->logger->error('Unhandled exception', [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        http_response_code(500);
        if ($this->isCli()) {
            fwrite(STDERR, 'Application error: ' . $exception->getMessage() . PHP_EOL);
        } else {
            echo 'An unexpected error occurred.';
        }
    }

    private function isCli(): bool
    {
        return PHP_SAPI === 'cli';
    }
}
