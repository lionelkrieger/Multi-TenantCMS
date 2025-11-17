<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Organization;
use App\Models\Property;
use App\Repositories\OrganizationRepository;
use App\Services\PropertyService;

final class PublicPropertyController extends Controller
{
    public function __construct(
        private readonly PropertyService $properties,
        private readonly OrganizationRepository $organizations
    ) {
    }

    public function search(?string $organizationId, ?string $term, int $page = 1): void
    {
        $organization = $this->resolveOrganization($organizationId);
        if ($organization === null) {
            $this->render('org/not-found', ['message' => 'Organization not available.']);
            return;
        }

        $page = max(1, $page);
        $perPage = 10;
        $limit = $perPage + 1;
        $offset = ($page - 1) * $perPage;
        $queryTerm = $term !== null ? trim($term) : '';

        $properties = $queryTerm !== ''
            ? $this->properties->search($organization->id, $queryTerm, $limit, $offset)
            : $this->properties->list($organization->id, $limit, $offset);

        $hasMore = count($properties) > $perPage;
        if ($hasMore) {
            array_pop($properties);
        }

        $this->render('public/search', [
            'organization' => $organization,
            'properties' => $properties,
            'term' => $queryTerm,
            'page' => $page,
            'hasMore' => $hasMore,
            'perPage' => $perPage,
        ]);
    }

    public function view(?string $organizationId, ?string $propertyId): void
    {
        $organization = $this->resolveOrganization($organizationId);
        if ($organization === null) {
            $this->render('org/not-found', ['message' => 'Organization not available.']);
            return;
        }

        $property = $this->resolveProperty($organization, $propertyId);
        if ($property === null) {
            $this->render('public/property/not-found', ['organization' => $organization]);
            return;
        }

        $this->render('public/property/detail', [
            'organization' => $organization,
            'property' => $property,
        ]);
    }

    public function actions(?string $organizationId, ?string $propertyId): void
    {
        $organization = $this->resolveOrganization($organizationId);
        if ($organization === null) {
            $this->render('org/not-found', ['message' => 'Organization not available.']);
            return;
        }

        $property = $this->resolveProperty($organization, $propertyId);
        if ($property === null) {
            $this->render('public/property/not-found', ['organization' => $organization]);
            return;
        }

        // Stub: extensions can hook into this view to render custom action/lead forms.
        $this->render('public/property/actions', [
            'organization' => $organization,
            'property' => $property,
        ]);
    }

    public function checkout(?string $organizationId, ?string $propertyId): void
    {
        $organization = $this->resolveOrganization($organizationId);
        if ($organization === null) {
            $this->render('org/not-found', ['message' => 'Organization not available.']);
            return;
        }

        $property = $this->resolveProperty($organization, $propertyId);
        if ($property === null) {
            $this->render('public/property/not-found', ['organization' => $organization]);
            return;
        }

        // Stub: this view simply signals where an extension-driven checkout should appear.
        $this->render('public/property/checkout', [
            'organization' => $organization,
            'property' => $property,
        ]);
    }
 
    public function confirmation(?string $organizationId, ?string $propertyId): void
    {
        $organization = $this->resolveOrganization($organizationId);
        if ($organization === null) {
            $this->render('org/not-found', ['message' => 'Organization not available.']);
            return;
        }

        $property = $this->resolveProperty($organization, $propertyId);
        if ($property === null) {
            $this->render('public/property/not-found', ['organization' => $organization]);
            return;
        }

        // Stub: extension confirmations can reuse this endpoint for clean URLs.
        $this->render('public/order/confirmation', [
            'organization' => $organization,
            'property' => $property,
        ]);
    }

    private function resolveOrganization(?string $organizationId): ?Organization
    {
        if ($organizationId !== null) {
            $organization = $this->organizations->findById($organizationId);
            if ($organization !== null) {
                return $organization;
            }
        }

        return resolve_organization_from_request();
    }

    private function resolveProperty(Organization $organization, ?string $propertyId): ?Property
    {
        $propertyId = $propertyId !== null ? trim($propertyId) : '';
        if ($propertyId === '') {
            return null;
        }

        return $this->properties->findById($organization->id, $propertyId);
    }
}
