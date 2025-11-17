<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Contracts\Arrayable;

final class EmployeeOrgAssignment implements Arrayable
{
    public function __construct(
        public readonly string $id,
        public readonly string $employeeUserId,
        public readonly string $organizationId,
        public readonly string $assignedBy,
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
            (string) ($record['employee_user_id'] ?? ''),
            (string) ($record['organization_id'] ?? ''),
            (string) ($record['assigned_by'] ?? ''),
            (string) ($record['assigned_at'] ?? date('Y-m-d H:i:s'))
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'employee_user_id' => $this->employeeUserId,
            'organization_id' => $this->organizationId,
            'assigned_by' => $this->assignedBy,
            'assigned_at' => $this->assignedAt,
        ];
    }
}
