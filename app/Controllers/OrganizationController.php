<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\OrganizationService;
use App\Services\PropertyService;

final class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizations,
        private readonly PropertyService $properties
    ) {
    }

    public function dashboard(string $organizationId): void
    {
        if (!\Auth::check()) {
            $this->redirect('/login.php');
        }

        $organization = $this->organizations->findById($organizationId);
        if ($organization === null) {
            $this->render('org/not-found', ['message' => 'Organization not found.']);
            return;
        }

        $propertyCount = $this->properties->count($organizationId);
        $recentProperties = $this->properties->recent($organizationId);

        $warnings = [];
        if ($propertyCount === 0) {
            $warnings[] = 'You have not added any properties yet. Start by creating your first property below.';
        }
        if ($organization->customDomain && ! $organization->domainVerified) {
            $warnings[] = sprintf(
                'Custom domain %s is pending verification. Update DNS records and re-check from the Settings page.',
                $organization->customDomain
            );
        }

        $this->render('org/dashboard', [
            'organization' => $organization,
            'stats' => [
                'property_count' => $propertyCount,
            ],
            'recentProperties' => $recentProperties,
            'warnings' => $warnings,
        ]);
    }
}
