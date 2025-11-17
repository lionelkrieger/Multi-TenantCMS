<?php

declare(strict_types=1);

final class Validator
{
    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function uuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-fA-F-]{36}$/', $value);
    }

    public static function string(string $value, int $min = 1, int $max = 255): bool
    {
        $length = mb_strlen($value);
        return $length >= $min && $length <= $max;
    }
}
