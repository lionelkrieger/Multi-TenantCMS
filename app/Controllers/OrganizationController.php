<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserInviteRepository;
use App\Repositories\UserRepository;
use App\Services\OrganizationService;
use App\Services\PropertyService;

final class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizations,
        private readonly PropertyService $properties,
        private readonly UserRepository $users,
        private readonly UserInviteRepository $invites
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
        $activeUsers = $this->users->countAllFiltered([
            'organization_id' => $organizationId,
            'status' => 'active',
        ]);
        $pendingInvites = $this->invites->countPendingForOrganization($organizationId);
        $recentInvites = $this->invites->listPendingByOrganization($organizationId, 5);
        $recentTeamMembers = $this->users->listByOrganization($organizationId, 5, 0);

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
                'active_user_count' => $activeUsers,
                'pending_invite_count' => $pendingInvites,
            ],
            'recentProperties' => $recentProperties,
            'recentInvites' => $recentInvites,
            'recentTeamMembers' => $recentTeamMembers,
            'warnings' => $warnings,
        ]);
    }
}
