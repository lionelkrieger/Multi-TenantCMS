<?php

declare(strict_types=1);

use App\Models\User;

final class Auth
{
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function login(User $user): void
    {
        session_manager()->start();
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_type'] = $user->userType;
        session_manager()->regenerate();
    }

    public static function logout(): void
    {
        session_manager()->destroy();
    }

    public static function id(): ?string
    {
        session_manager()->start();
        return $_SESSION['user_id'] ?? null;
    }

    public static function userType(): ?string
    {
        session_manager()->start();
        return $_SESSION['user_type'] ?? null;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }
}
