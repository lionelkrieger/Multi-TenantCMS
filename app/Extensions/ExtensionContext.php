<?php

declare(strict_types=1);

namespace App\Extensions;

use App\Support\Logger;
use PDO;

final class ExtensionContext
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        public readonly string $extensionId,
        public readonly string $extensionSlug,
        public readonly ?string $organizationId,
        public readonly PDO $connection,
        public readonly Logger $logger,
        public readonly array $config = []
    ) {
    }
}
