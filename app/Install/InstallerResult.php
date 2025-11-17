<?php

declare(strict_types=1);

namespace App\Install;

final class InstallerResult
{
    public function __construct(
        public readonly bool $appPasswordGenerated,
        public readonly string $appDbPassword,
        public readonly string $masterAdminEmail
    ) {
    }
}
