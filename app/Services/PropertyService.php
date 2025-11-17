<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;
use App\Models\Property;
use App\Repositories\OrganizationRepository;
use App\Repositories\PropertyRepository;
use RuntimeException;

final class PropertyService
{
    public function __construct(
        private readonly PropertyRepository $properties,
        private readonly OrganizationRepository $organizations
    ) {
    }

    public function findById(string $organizationId, string $propertyId): ?Property
    {
        return $this->properties->findById($propertyId, $organizationId);
    }

    /**
     * @return array<int, Property>
     */
    public function list(string $organizationId, int $limit = 50, int $offset = 0): array
    {
        return $this->properties->listByOrganization($organizationId, $limit, $offset);
    }

    /**
     * @return array<int, Property>
     */
    public function search(string $organizationId, string $term, int $limit = 50, int $offset = 0): array
    {
        $trimmed = trim($term);
        if ($trimmed === '') {
            return $this->list($organizationId, $limit, $offset);
        }

        return $this->properties->searchByOrganization($organizationId, $trimmed, $limit, $offset);
    }

    public function create(string $organizationId, string $name, ?string $description = null, ?string $address = null): Property
    {
        $organization = $this->organizations->findById($organizationId);
        if ($organization === null) {
            throw new RuntimeException('Organization not found.');
        }

        $property = new Property(
            \generate_uuid_v4(),
            $name,
            $description,
            $address,
            $organizationId,
            date('Y-m-d H:i:s')
        );

        $this->properties->create($property);
        return $property;
    }

    public function update(string $organizationId, string $propertyId, array $payload): Property
    {
        $property = $this->properties->findById($propertyId, $organizationId);
        if ($property === null) {
            throw new RuntimeException('Property not found.');
        }

        $updated = new Property(
            $property->id,
            $payload['name'] ?? $property->name,
            $payload['description'] ?? $property->description,
            $payload['address'] ?? $property->address,
            $property->organizationId,
            $property->createdAt
        );

        $this->properties->update($updated);
        return $updated;
    }

    public function delete(string $organizationId, string $propertyId): void
    {
        $this->properties->delete($propertyId, $organizationId);
    }

    public function count(string $organizationId): int
    {
        return $this->properties->countByOrganization($organizationId);
    }

    /**
     * @return array<int, Property>
     */
    public function recent(string $organizationId, int $limit = 5): array
    {
        return $this->properties->recentByOrganization($organizationId, $limit);
    }

    public function organization(string $organizationId): ?Organization
    {
        return $this->organizations->findById($organizationId);
    }
}
