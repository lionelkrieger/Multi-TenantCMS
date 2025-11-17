<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\OrganizationRepository;
use App\Repositories\UserRepository;
use Throwable;

final class MasterAdminController extends AdminController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly OrganizationRepository $organizations
    ) {
    }

    public function dashboard(): void
    {
        $this->ensureMasterAdmin();

        try {
            $metrics = [
                'totalOrganizations' => $this->organizations->countAll(),
                'totalUsers' => $this->users->countAll(),
            ];
            $recentOrganizations = $this->organizations->recent(5);
            $recentUsers = $this->users->recent(5);
        } catch (Throwable $exception) {
            app_logger()->error('Unable to load master admin dashboard metrics', [
                'exception' => $exception->getMessage(),
            ]);

            $metrics = [
                'totalOrganizations' => 0,
                'totalUsers' => 0,
            ];
            $recentOrganizations = [];
            $recentUsers = [];
        }

        $this->render('admin/dashboard', [
            'metrics' => $metrics,
            'recentOrganizations' => $recentOrganizations,
            'recentUsers' => $recentUsers,
        ]);
    }
}
