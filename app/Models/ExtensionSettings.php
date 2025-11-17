<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Contracts\Arrayable;

final class ExtensionSettings implements Arrayable
{
    public function __construct(
        public readonly string $id,
        public readonly string $extensionId,
        public readonly string $organizationId,
        public readonly string $key,
        public readonly mixed $value,
        public readonly string $createdAt,
        public readonly string $updatedAt
    ) {
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function fromArray(array $record): self
    {
        $value = $record['value'] ?? null;
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $value = $decoded ?? $value;
        }

        return new self(
            (string) ($record['id'] ?? ''),
            (string) ($record['extension_id'] ?? ''),
            (string) ($record['organization_id'] ?? ''),
            (string) ($record['key'] ?? ''),
            $value,
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
            'key' => $this->key,
            'value' => $this->value,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
