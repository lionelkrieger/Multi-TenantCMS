<?php

declare(strict_types=1);

namespace App\Extensions\Contracts;

interface ExtensionRegistryInterface
{
    public function discover(): void;
    public function getExtension(string $slug): ?object;
    public function getActiveExtensions(): array;
}
