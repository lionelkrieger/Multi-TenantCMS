<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\OrganizationRepository;
use App\Repositories\UserInviteRepository;
use App\Repositories\UserRepository;
use App\Services\UserInviteService;
use App\Support\RequestValidator;
use Throwable;

final class UserController extends AdminController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserInviteRepository $invites,
        private readonly UserInviteService $inviteService,
        private readonly OrganizationRepository $organizations
    ) {
    }

    public function index(array $query, array $input): void
    {
        $this->ensureMasterAdmin();

        $filters = $this->extractUserFilters($query);
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = max(1, min(50, (int) ($query['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $users = $this->users->listAll($filters, $limit, $offset);
        $totalUsers = $this->users->countAllFiltered($filters);

        $organizations = $this->organizations->listAll(100, 0, null);
        $organizationMap = [];
        foreach ($organizations as $organization) {
            $organizationMap[$organization->id] = $organization;
        }

        $invitePage = max(1, (int) ($query['inv_page'] ?? 1));
        $inviteLimit = max(1, min(25, (int) ($query['inv_limit'] ?? 10)));
        $inviteOffset = ($invitePage - 1) * $inviteLimit;
        $inviteSearch = isset($query['inv_q']) ? trim((string) $query['inv_q']) : null;
        $inviteSearch = $inviteSearch === '' ? null : $inviteSearch;

        $pendingInvites = $this->invites->listPending($inviteSearch, $inviteLimit, $inviteOffset);
        $pendingInviteTotal = $this->invites->countPending($inviteSearch);

        $inviteForm = [
            'values' => [
                'email' => $input['email'] ?? '',
                'invite_type' => $input['invite_type'] ?? 'org_admin',
                'organization_id' => $input['organization_id'] ?? '',
            ],
            'errors' => [],
        ];

        $flash = [
            'success' => null,
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->handlePost($input);
            $inviteForm['errors'] = $result['errors'];
            $inviteForm['values'] = array_merge($inviteForm['values'], $result['values']);

            if ($result['success']) {
                $this->redirect('/admin/users.php?invited=1');
            }
        }

        if (isset($query['invited'])) {
            $flash['success'] = 'Invitation sent successfully.';
        } elseif (isset($query['revoked'])) {
            $flash['success'] = 'Invitation revoked.';
        }

        $this->render('admin/users', [
            'users' => $users,
            'pagination' => [
                'total' => $totalUsers,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => (int) max(1, ceil($totalUsers / $limit)),
                'filters' => $filters,
            ],
            'organizationMap' => $organizationMap,
            'organizations' => $organizations,
            'pendingInvites' => $pendingInvites,
            'invitePagination' => [
                'total' => $pendingInviteTotal,
                'page' => $invitePage,
                'limit' => $inviteLimit,
                'total_pages' => (int) max(1, ceil($pendingInviteTotal / $inviteLimit)),
                'search' => $inviteSearch,
            ],
            'inviteForm' => $inviteForm,
            'flash' => $flash,
            'csrfToken' => \CSRF::token(),
        ]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function extractUserFilters(array $query): array
    {
        $filters = [];
        $search = isset($query['q']) ? trim((string) $query['q']) : null;
        if ($search !== null && $search !== '') {
            $filters['search'] = $search;
        }

        $userType = isset($query['role']) ? trim((string) $query['role']) : null;
        if ($userType !== null && in_array($userType, ['master_admin', 'org_admin', 'employee', 'user'], true)) {
            $filters['user_type'] = $userType;
        }

        $status = isset($query['status']) ? trim((string) $query['status']) : null;
        if ($status !== null && in_array($status, ['active', 'unassigned', 'deleted'], true)) {
            $filters['status'] = $status;
        }

        $organizationId = isset($query['org_id']) ? trim((string) $query['org_id']) : null;
        if ($organizationId !== null && $organizationId !== '') {
            $filters['organization_id'] = $organizationId;
        }

        return $filters;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: bool, errors: array<string, string>, values: array<string, string>}
     */
    private function handlePost(array $input): array
    {
        $action = $input['action'] ?? 'invite';

        if ($action === 'revoke') {
            return $this->handleRevoke($input);
        }

        return $this->handleInvite($input);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: bool, errors: array<string, string>, values: array<string, string>}
     */
    private function handleInvite(array $input): array
    {
        $errors = [];

        if (!\CSRF::validate($input['csrf_token'] ?? '')) {
            $errors['general'] = 'Invalid security token. Please refresh and try again.';
            return ['success' => false, 'errors' => $errors, 'values' => $input];
        }

        $email = RequestValidator::email($input['email'] ?? '');
        if ($email === null) {
            $errors['email'] = 'Please provide a valid email address.';
        }

        $inviteType = isset($input['invite_type']) ? strtolower((string) $input['invite_type']) : '';
        if (!in_array($inviteType, ['org_admin', 'employee', 'user'], true)) {
            $errors['invite_type'] = 'Invalid invite type selected.';
        }

        $organizationId = isset($input['organization_id']) ? trim((string) $input['organization_id']) : '';
        if (in_array($inviteType, ['org_admin', 'employee'], true) && $organizationId === '') {
            $errors['organization_id'] = 'Organization is required for this role.';
        }

        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors, 'values' => $input];
        }

        try {
            $inviterId = \Auth::id();
            if ($inviterId === null) {
                throw new \RuntimeException('Unable to determine authenticated user.');
            }

            $this->inviteService->issueInvite(
                $email,
                $inviteType,
                $organizationId === '' ? null : $organizationId,
                $inviterId
            );

            return ['success' => true, 'errors' => [], 'values' => $input];
        } catch (Throwable $throwable) {
            $errors['general'] = $throwable->getMessage();
            return ['success' => false, 'errors' => $errors, 'values' => $input];
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: bool, errors: array<string, string>, values: array<string, string>}
     */
    private function handleRevoke(array $input): array
    {
        $errors = [];
        if (!\CSRF::validate($input['csrf_token'] ?? '')) {
            $errors['general'] = 'Invalid security token. Please refresh and try again.';
            return ['success' => false, 'errors' => $errors, 'values' => $input];
        }

        $inviteId = isset($input['invite_id']) ? trim((string) $input['invite_id']) : '';
        if ($inviteId === '') {
            $errors['general'] = 'Invalid invite selected.';
            return ['success' => false, 'errors' => $errors, 'values' => $input];
        }

        try {
            $this->inviteService->revokeInvite($inviteId);
            $this->redirect('/admin/users.php?revoked=1');
        } catch (Throwable $throwable) {
            $errors['general'] = $throwable->getMessage();
        }

        return ['success' => false, 'errors' => $errors, 'values' => $input];
    }
}
