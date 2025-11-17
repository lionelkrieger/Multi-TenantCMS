<?php

declare(strict_types=1);

function app_config(): array
{
    static $config = null;

    if ($config === null) {
        $configPath = config_path('app.php');
        if (!file_exists($configPath)) {
            throw new RuntimeException('Application config not found. Run the installer at /install.php to generate app/config/app.php and app/config/database.php.');
        }
        $config = require $configPath;
    }

    return $config;
}

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__, 2);
    return $path ? $base . '/' . ltrim($path, '/') : $base;
}

function app_path(string $path = ''): string
{
    $base = dirname(__DIR__);
    return $path ? $base . '/' . ltrim($path, '/') : $base;
}

function public_path(string $path = ''): string
{
    $base = dirname(__DIR__, 2) . '/public_html';
    return $path ? $base . '/' . ltrim($path, '/') : $base;
}

function storage_path(string $path = ''): string
{
    $base = dirname(__DIR__) . '/uploads';
    return $path ? $base . '/' . ltrim($path, '/') : $base;
}

function view_path(string $path = ''): string
{
    $base = dirname(__DIR__, 2) . '/views';
    return $path ? $base . '/' . ltrim($path, '/') : $base;
}

function config_path(string $path = ''): string
{
    $base = dirname(__DIR__) . '/config';
    return $path ? $base . '/' . ltrim($path, '/') : $base;
}

function logger(string $message, array $context = []): void
{
    app_logger()->info($message, $context);
}

function app_logger(): \App\Support\Logger
{
    static $logger = null;

    if ($logger === null) {
        $config = app_config();
        $logPath = $config['log_path'] ?? dirname(__DIR__) . '/logs/application.log';
        $logger = new \App\Support\Logger($logPath);
    }

    return $logger;
}

function audit_logger(): \App\Support\AuditLogger
{
    static $instance = null;

    if ($instance === null) {
        $instance = new \App\Support\AuditLogger();
    }

    return $instance;
}

function audit_log(string $event, array $context = []): void
{
    audit_logger()->log($event, $context);
}

function sanitize(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function generate_uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function random_token(int $length = 32): string
{
    $bytes = max(1, (int) ceil($length / 2));
    return bin2hex(random_bytes($bytes));
}

function session_manager(): \App\Support\SessionManager
{
    static $instance = null;

    if ($instance === null) {
        $config = app_config();
        $instance = new \App\Support\SessionManager($config['session'] ?? []);
    }

    return $instance;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}
