<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;
use App\Repositories\OrganizationRepository;
use App\Repositories\UserRepository;
use RuntimeException;

final class OrganizationService
{
    public function __construct(
        private readonly OrganizationRepository $organizations,
        private readonly UserRepository $users
    ) {
    }

    public function findById(string $id): ?Organization
    {
        return $this->organizations->findById($id);
    }

    /**
     * @return array<int, Organization>
     */
    public function listOrganizations(int $limit = 25, int $offset = 0): array
    {
        return $this->organizations->listAll($limit, $offset);
    }

    public function createOrganization(string $name, string $creatorUserId, array $branding = []): Organization
    {
        $creator = $this->users->findById($creatorUserId);
        if ($creator === null) {
            throw new RuntimeException('Creator user not found.');
        }

        $now = date('Y-m-d H:i:s');
        $organization = new Organization(
            \generate_uuid_v4(),
            $name,
            $creatorUserId,
            $branding['logo_url'] ?? null,
            $branding['primary_color'] ?? '#0066cc',
            $branding['secondary_color'] ?? '#f8f9fa',
            $branding['accent_color'] ?? '#dc3545',
            $branding['font_family'] ?? 'Roboto, sans-serif',
            $branding['show_branding'] ?? true,
            $branding['custom_css'] ?? null,
            $branding['custom_domain'] ?? null,
            false,
            null,
            null,
            'none',
            null,
            $now,
            $now
        );

        $this->organizations->create($organization);
        return $organization;
    }

    public function updateBranding(string $organizationId, array $payload): void
    {
        $organization = $this->organizations->findById($organizationId);
        if ($organization === null) {
            throw new RuntimeException('Organization not found.');
        }

        $this->organizations->updateBranding($organizationId, $payload);
    }
}
