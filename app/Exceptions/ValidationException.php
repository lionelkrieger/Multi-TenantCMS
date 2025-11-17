<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    public static function fromField(string $field): self
    {
        return new self(sprintf('Invalid value for %s', $field));
    }
}
