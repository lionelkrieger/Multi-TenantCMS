<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserFlow;
use App\Repositories\OrganizationRepository;
use App\Repositories\PropertyRepository;
use App\Repositories\UserFlowRepository;
use RuntimeException;

final class UserFlowService
{
    public function __construct(
        private readonly UserFlowRepository $flows,
        private readonly OrganizationRepository $organizations,
        private readonly PropertyRepository $properties
    ) {
    }

    public function startFlow(string $organizationId, string $propertyId, ?string $userId = null, ?string $message = null): UserFlow
    {
        $organization = $this->organizations->findById($organizationId);
        if ($organization === null) {
            throw new RuntimeException('Organization not found.');
        }

        $property = $this->properties->findById($propertyId, $organizationId);
        if ($property === null) {
            throw new RuntimeException('Property not found for this organization.');
        }

        $id = generate_uuid_v4();
        $token = random_token(64);
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $flow = new UserFlow(
            $id,
            $token,
            $organizationId,
            $propertyId,
            $userId,
            'actions',
            null,
            $expiresAt,
            $now
        );

        $this->flows->create($flow);

        logger('User flow started', [
            'organization_id' => $organizationId,
            'property_id' => $propertyId,
            'user_id' => $userId,
            'message' => $message,
        ]);

        return $flow;
    }

    public function getFlowForProperty(string $token, string $organizationId, string $propertyId): UserFlow
    {
        $flow = $this->flows->findByToken($token);
        if ($flow === null) {
            throw new RuntimeException('Flow token not found.');
        }

        if ($flow->organizationId !== $organizationId || $flow->propertyId !== $propertyId) {
            throw new RuntimeException('Flow does not match the requested resource.');
        }

        if ($flow->isExpired()) {
            throw new RuntimeException('Flow token expired.');
        }

        return $flow;
    }

    /**
     * @param UserFlow $flow
     * @param string $nextStep
     * @param string $completedStep
     */
    public function advanceFlow(UserFlow $flow, string $nextStep, string $completedStep): UserFlow
    {
        $completed = $flow->completedSteps ?? [];
        if (!in_array($completedStep, $completed, true)) {
            $completed[] = $completedStep;
        }

        $this->flows->updateCurrentStep($flow->id, $nextStep, $completed);

        logger('User flow advanced', [
            'flow_id' => $flow->id,
            'token' => $flow->token,
            'next_step' => $nextStep,
            'completed_steps' => $completed,
        ]);

        return new UserFlow(
            $flow->id,
            $flow->token,
            $flow->organizationId,
            $flow->propertyId,
            $flow->userId,
            $nextStep,
            $completed,
            $flow->expiresAt,
            $flow->createdAt
        );
    }

    public function completeFlow(UserFlow $flow): void
    {
        $this->flows->delete($flow->id);
        logger('User flow completed', [
            'flow_id' => $flow->id,
            'token' => $flow->token,
        ]);
    }
}
