<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ValidationException;

final class RequestValidator
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, callable> $rules
     *
     * @return array<string, mixed>
     */
    public static function validate(array $input, array $rules): array
    {
        $validated = [];
        foreach ($rules as $field => $rule) {
            $value = $input[$field] ?? null;
            $result = $rule($value);
            if ($result === null) {
                throw ValidationException::fromField($field);
            }
            $validated[$field] = $result;
        }

        return $validated;
    }

    public static function stringOrNull(mixed $value, int $min = 0, int $max = 255): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '' && $min === 0) {
            return null;
        }

        $length = mb_strlen($value);
        if ($length < $min || $length > $max) {
            return null;
        }

        return $value;
    }

    public static function sanitizedString(mixed $value, int $min = 1, int $max = 255): ?string
    {
        $value = self::stringOrNull($value, $min, $max);
        return $value === null ? null : sanitize($value);
    }

    public static function email(mixed $value): ?string
    {
        $value = trim((string) $value);
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? strtolower($value) : null;
    }

    public static function hexColor(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^#?[0-9a-fA-F]{6}$/', $value)) {
            return null;
        }

        $color = strtoupper($value);
        return str_starts_with($color, '#') ? $color : '#' . $color;
    }

    public static function domain(mixed $value, bool $allowNull = true): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return $allowNull ? null : null;
        }

        if (!preg_match('/^(?=.{1,255}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $value)) {
            return null;
        }

        return strtolower($value);
    }
}
