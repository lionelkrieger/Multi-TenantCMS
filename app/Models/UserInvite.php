<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Contracts\Arrayable;

final class UserInvite implements Arrayable
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly ?string $organizationId,
        public readonly string $inviterUserId,
        public readonly string $inviteType,
        public readonly string $token,
        public readonly string $status,
        public readonly string $createdAt,
        public readonly ?string $expiresAt
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
            isset($record['organization_id']) ? (string) $record['organization_id'] : null,
            (string) ($record['inviter_user_id'] ?? ''),
            (string) ($record['invite_type'] ?? 'user'),
            (string) ($record['token'] ?? ''),
            (string) ($record['status'] ?? 'pending'),
            (string) ($record['created_at'] ?? date('Y-m-d H:i:s')),
            isset($record['expires_at']) ? (string) $record['expires_at'] : null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'organization_id' => $this->organizationId,
            'inviter_user_id' => $this->inviterUserId,
            'invite_type' => $this->inviteType,
            'token' => $this->token,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
        ];
    }
}
