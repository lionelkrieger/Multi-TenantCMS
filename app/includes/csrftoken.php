<?php

declare(strict_types=1);

final class CSRF
{
    public static function token(): string
    {
        session_manager()->start();

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function validate(string $token): bool
    {
        session_manager()->start();

        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
