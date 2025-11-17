<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\OrganizationService;

final class OrganizationUsersController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizations,
        private readonly UserRepository $users
    ) {
    }

    public function index(string $organizationId): void
    {
        $this->authorize($organizationId);

        $organization = $this->organizations->findById($organizationId);
        if ($organization === null) {
            $this->render('org/not-found', ['message' => 'Organization not found.']);
            return;
        }

        $members = $this->users->listByOrganization($organizationId, 50);

        $this->render('org/users', [
            'organization' => $organization,
            'users' => $members,
        ]);
    }

    private function authorize(string $organizationId): User
    {
        if (!\Auth::check()) {
            $this->redirect('/login.php');
        }

        $userId = \Auth::id();
        if ($userId === null) {
            $this->redirect('/login.php');
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            $this->redirect('/login.php');
        }

        if ($user->userType === 'master_admin') {
            return $user;
        }

        if (in_array($user->userType, ['org_admin', 'employee'], true) && $user->organizationId === $organizationId) {
            return $user;
        }

        http_response_code(403);
        $this->render('errors/forbidden');
        exit;
    }
}
