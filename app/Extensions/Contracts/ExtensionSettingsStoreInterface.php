<?php

declare(strict_types=1);

namespace App\Extensions\Contracts;

interface ExtensionSettingsStoreInterface
{
    public function get(string $extensionSlug, string $organizationId, string $key, mixed $default = null): mixed;

    public function set(string $extensionSlug, string $organizationId, string $key, mixed $value, bool $encrypt = false): void;

    /** @return array<string, mixed> */
    public function all(string $extensionSlug, string $organizationId): array;

    public function setEnabled(string $extensionSlug, string $organizationId, bool $enabled): void;
}
