<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Contracts\Arrayable;

final class UserFlow implements Arrayable
{
    /** @param array<int, string>|null $completedSteps */
    public function __construct(
        public readonly string $id,
        public readonly string $token,
        public readonly string $organizationId,
        public readonly string $propertyId,
        public readonly ?string $userId,
        public readonly string $currentStep,
        public readonly ?array $completedSteps,
        public readonly string $expiresAt,
        public readonly string $createdAt
    ) {
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function fromArray(array $record): self
    {
        $completed = $record['completed_steps'] ?? null;
        if (is_string($completed)) {
            $decoded = json_decode($completed, true);
            $completed = is_array($decoded) ? array_values(array_map('strval', $decoded)) : null;
        }

        return new self(
            (string) ($record['id'] ?? ''),
            (string) ($record['token'] ?? ''),
            (string) ($record['org_id'] ?? ''),
            (string) ($record['property_id'] ?? ''),
            isset($record['user_id']) ? (string) $record['user_id'] : null,
            (string) ($record['current_step'] ?? 'actions'),
            $completed,
            (string) ($record['expires_at'] ?? date('Y-m-d H:i:s')),
            (string) ($record['created_at'] ?? date('Y-m-d H:i:s'))
        );
    }

    public function isExpired(): bool
    {
        return strtotime($this->expiresAt) < time();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'org_id' => $this->organizationId,
            'property_id' => $this->propertyId,
            'user_id' => $this->userId,
            'current_step' => $this->currentStep,
            'completed_steps' => $this->completedSteps,
            'expires_at' => $this->expiresAt,
            'created_at' => $this->createdAt,
        ];
    }
}
