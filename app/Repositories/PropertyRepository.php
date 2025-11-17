<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Property;

final class PropertyRepository extends Repository
{
    public function findById(string $id, string $organizationId): ?Property
    {
        $record = $this->fetchOne(
            'SELECT * FROM properties WHERE id = :id AND organization_id = :organization_id LIMIT 1',
            [
                'id' => $id,
                'organization_id' => $organizationId,
            ]
        );

        return $record ? Property::fromArray($record) : null;
    }

    public function listByOrganization(string $organizationId, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        $records = $this->fetchAll(
            sprintf(
                'SELECT * FROM properties WHERE organization_id = :organization_id ORDER BY created_at DESC LIMIT %d OFFSET %d',
                $limit,
                $offset
            ),
            ['organization_id' => $organizationId]
        );

        return array_map(static fn (array $record): Property => Property::fromArray($record), $records);
    }

    public function recentByOrganization(string $organizationId, int $limit = 5): array
    {
        $limit = max(1, min(25, $limit));
        $records = $this->fetchAll(
            sprintf(
                'SELECT * FROM properties WHERE organization_id = :organization_id ORDER BY created_at DESC LIMIT %d',
                $limit
            ),
            ['organization_id' => $organizationId]
        );

        return array_map(static fn (array $record): Property => Property::fromArray($record), $records);
    }

    public function searchByOrganization(string $organizationId, string $term, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        $records = $this->fetchAll(
            sprintf(
                'SELECT * FROM properties WHERE organization_id = :organization_id AND (name LIKE :term OR address LIKE :term) ORDER BY created_at DESC LIMIT %d OFFSET %d',
                $limit,
                $offset
            ),
            [
                'organization_id' => $organizationId,
                'term' => sprintf('%%%s%%', $term),
            ]
        );

        return array_map(static fn (array $record): Property => Property::fromArray($record), $records);
    }

    public function create(Property $property): void
    {
        $this->insert(
            'INSERT INTO properties (id, name, description, address, organization_id, created_at) VALUES (:id, :name, :description, :address, :organization_id, :created_at)',
            [
                'id' => $property->id,
                'name' => $property->name,
                'description' => $property->description,
                'address' => $property->address,
                'organization_id' => $property->organizationId,
                'created_at' => $property->createdAt,
            ]
        );
    }

    public function update(Property $property): void
    {
        $this->update(
            'UPDATE properties SET name = :name, description = :description, address = :address WHERE id = :id AND organization_id = :organization_id',
            [
                'id' => $property->id,
                'name' => $property->name,
                'description' => $property->description,
                'address' => $property->address,
                'organization_id' => $property->organizationId,
            ]
        );
    }

    public function delete(string $id, string $organizationId): void
    {
        parent::delete(
            'DELETE FROM properties WHERE id = :id AND organization_id = :organization_id',
            [
                'id' => $id,
                'organization_id' => $organizationId,
            ]
        );
    }

    public function countByOrganization(string $organizationId): int
    {
        return (int) ($this->fetchColumn(
            'SELECT COUNT(*) FROM properties WHERE organization_id = :organization_id',
            ['organization_id' => $organizationId]
        ) ?? 0);
    }
}
