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

    public function install(string $slug): void;

    public function upgrade(string $slug): void;

    public function uninstall(string $slug): void;

    public function activate(string $slug, string $organizationId): void;

    public function deactivate(string $slug, string $organizationId): void;

    /**
     * @return array<int, array{surface: string, method: string, path: string, handler: callable, capability: ?string, metadata: array}>
     */
    public function runtimeRoutes(string $slug, ?string $organizationId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function doctor(?string $slug = null): array;
}
