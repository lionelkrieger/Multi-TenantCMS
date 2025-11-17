<?php

declare(strict_types=1);

namespace App\Extensions\Contracts;

use App\Models\Extension;

interface ExtensionRegistryInterface
{
    /** @return Extension[] */
    public function all(): array;

    public function discover(): void;

    public function findBySlug(string $slug): ?Extension;

    public function activate(string $slug, string $organizationId): void;

    public function deactivate(string $slug, string $organizationId): void;
}
