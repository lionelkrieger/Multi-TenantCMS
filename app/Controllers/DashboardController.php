<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\OrganizationRepository;
use App\Repositories\UserRepository;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly OrganizationRepository $organizations
    ) {
    }

    public function index(): void
    {
        if (!\Auth::check()) {
            $this->redirect('/login.php');
        }

        $user = $this->users->findById(\Auth::id() ?? '');
        $organizations = $user && $user->organizationId
            ? [$this->organizations->findById($user->organizationId)]
            : $this->organizations->listAll(10, 0);

        $this->render('dashboard/home', [
            'user' => $user,
            'organizations' => array_filter($organizations),
        ]);
    }
}
