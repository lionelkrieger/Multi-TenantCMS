<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\UserFlow;

final class UserFlowRepository extends Repository
{
    public function findByToken(string $token): ?UserFlow
    {
        $record = $this->fetchOne('SELECT * FROM user_flows WHERE token = :token LIMIT 1', ['token' => $token]);
        return $record ? UserFlow::fromArray($record) : null;
    }

    public function create(UserFlow $flow): void
    {
        $this->insert(
            'INSERT INTO user_flows (id, token, org_id, property_id, user_id, current_step, completed_steps, expires_at, created_at) VALUES (:id, :token, :org_id, :property_id, :user_id, :current_step, :completed_steps, :expires_at, :created_at)',
            [
                'id' => $flow->id,
                'token' => $flow->token,
                'org_id' => $flow->organizationId,
                'property_id' => $flow->propertyId,
                'user_id' => $flow->userId,
                'current_step' => $flow->currentStep,
                'completed_steps' => $flow->completedSteps ? json_encode($flow->completedSteps, JSON_THROW_ON_ERROR) : null,
                'expires_at' => $flow->expiresAt,
                'created_at' => $flow->createdAt,
            ]
        );
    }

    public function updateCurrentStep(string $flowId, string $currentStep, array $completedSteps): void
    {
        $this->update(
            'UPDATE user_flows SET current_step = :current_step, completed_steps = :completed_steps WHERE id = :id',
            [
                'current_step' => $currentStep,
                'completed_steps' => json_encode($completedSteps, JSON_THROW_ON_ERROR),
                'id' => $flowId,
            ]
        );
    }

    public function delete(string $flowId): void
    {
        $this->delete('DELETE FROM user_flows WHERE id = :id', ['id' => $flowId]);
    }
}
