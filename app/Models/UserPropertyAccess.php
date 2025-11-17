<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Contracts\Arrayable;

final class UserPropertyAccess implements Arrayable
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $propertyId,
        public readonly string $assignedAt
    ) {
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function fromArray(array $record): self
    {
        return new self(
            (string) ($record['id'] ?? ''),
            (string) ($record['user_id'] ?? ''),
            (string) ($record['property_id'] ?? ''),
            (string) ($record['assigned_at'] ?? date('Y-m-d H:i:s'))
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'property_id' => $this->propertyId,
            'assigned_at' => $this->assignedAt,
        ];
    }
}
