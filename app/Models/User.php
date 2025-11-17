<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Contracts\Arrayable;

final class User implements Arrayable
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly ?string $name,
        public readonly ?string $organizationId,
        public readonly string $userType,
        public readonly string $status,
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
            strtolower((string) ($record['email'] ?? '')),
            (string) ($record['password_hash'] ?? ''),
            isset($record['name']) ? (string) $record['name'] : null,
            isset($record['organization_id']) ? (string) $record['organization_id'] : null,
            (string) ($record['user_type'] ?? 'user'),
            (string) ($record['status'] ?? 'active'),
            (string) ($record['created_at'] ?? date('Y-m-d H:i:s'))
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'password_hash' => $this->passwordHash,
            'name' => $this->name,
            'organization_id' => $this->organizationId,
            'user_type' => $this->userType,
            'status' => $this->status,
            'created_at' => $this->createdAt,
        ];
    }
}
