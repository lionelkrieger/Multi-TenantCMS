<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Contracts\Arrayable;

final class ExtensionSettings implements Arrayable
{
    /** @param array<string, mixed> $settings */
    public function __construct(
        public readonly string $id,
        public readonly string $extensionId,
        public readonly string $organizationId,
        public readonly array $settings,
        public readonly bool $enabled,
        public readonly string $createdAt,
        public readonly string $updatedAt
    ) {
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function fromArray(array $record): self
    {
        $settings = $record['settings'] ?? [];
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        return new self(
            (string) ($record['id'] ?? ''),
            (string) ($record['extension_id'] ?? ''),
            (string) ($record['organization_id'] ?? ''),
            $settings,
            (bool) ($record['enabled'] ?? false),
            (string) ($record['created_at'] ?? date('Y-m-d H:i:s')),
            (string) ($record['updated_at'] ?? date('Y-m-d H:i:s'))
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'extension_id' => $this->extensionId,
            'organization_id' => $this->organizationId,
            'settings' => $this->settings,
            'enabled' => $this->enabled,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
