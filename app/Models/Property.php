<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Contracts\Arrayable;

final class Property implements Arrayable
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $address,
        public readonly string $organizationId,
        public readonly string $createdAt
    ) {
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function fromArray(array $record): self
    {
        return new self(
            (string) ($record['id'] ?? ''),
            (string) ($record['name'] ?? ''),
            isset($record['description']) ? (string) $record['description'] : null,
            isset($record['address']) ? (string) $record['address'] : null,
            (string) ($record['organization_id'] ?? ''),
            (string) ($record['created_at'] ?? date('Y-m-d H:i:s'))
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'address' => $this->address,
            'organization_id' => $this->organizationId,
            'created_at' => $this->createdAt,
        ];
    }
}
