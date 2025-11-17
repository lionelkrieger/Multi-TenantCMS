<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\OrganizationRepository;
use App\Repositories\UserRepository;
use App\Services\OrganizationService;
use App\Support\RequestValidator;
use Throwable;

final class OrganizationController extends AdminController
{
    public function __construct(
        private readonly OrganizationRepository $organizations,
        private readonly OrganizationService $organizationService,
        private readonly UserRepository $users
    ) {
    }

    public function index(array $query, array $input): void
    {
        $this->ensureMasterAdmin();

        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = max(1, min(50, (int) ($query['limit'] ?? 25)));
        $search = isset($query['q']) ? trim((string) $query['q']) : null;
        $search = $search === '' ? null : $search;
        $offset = ($page - 1) * $limit;

        $errors = [];
        $formValues = $this->buildFormValues($input);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $created = $this->handleCreate($input);
            $errors = $created['errors'];
            $formValues = $created['values'];

            if ($created['success']) {
                $this->redirect('/admin/organizations.php?created=1');
            }
        }

        $total = $this->organizations->countAll($search);
        $organizations = $this->organizations->listAll($limit, $offset, $search);
        $creatorIds = array_filter(array_map(static fn ($organization) => $organization->createdBy, $organizations));
        $creators = $this->users->findByIds($creatorIds);

        $this->render('admin/organizations', [
            'organizations' => $organizations,
            'creators' => $creators,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => (int) max(1, ceil($total / $limit)),
                'search' => $search,
            ],
            'form' => [
                'values' => $formValues,
                'errors' => $errors,
            ],
            'flash' => [
                'success' => isset($query['created']) ? 'Organization created successfully.' : null,
            ],
            'csrfToken' => \CSRF::token(),
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: bool, errors: array<string, string>, values: array<string, string>}
     */
    private function handleCreate(array $input): array
    {
        $errors = [];

        if (!\CSRF::validate($input['csrf_token'] ?? '')) {
            $errors['general'] = 'Invalid security token. Please refresh and try again.';
            return ['success' => false, 'errors' => $errors, 'values' => $this->buildFormValues($input)];
        }

        $name = RequestValidator::sanitizedString($input['name'] ?? null, 1, 255);
        if ($name === null) {
            $errors['name'] = 'Organization name is required.';
        }

        [$primaryColor, $primaryError] = $this->validateColor($input['primary_color'] ?? '', '#0066CC');
        if ($primaryError !== null) {
            $errors['primary_color'] = $primaryError;
        }

        [$secondaryColor, $secondaryError] = $this->validateColor($input['secondary_color'] ?? '', '#F8F9FA');
        if ($secondaryError !== null) {
            $errors['secondary_color'] = $secondaryError;
        }

        [$accentColor, $accentError] = $this->validateColor($input['accent_color'] ?? '', '#DC3545');
        if ($accentError !== null) {
            $errors['accent_color'] = $accentError;
        }

        $customDomainInput = trim((string) ($input['custom_domain'] ?? ''));
        $customDomain = null;
        if ($customDomainInput !== '') {
            $customDomain = RequestValidator::domain($customDomainInput, false);
            if ($customDomain === null) {
                $errors['custom_domain'] = 'Enter a valid domain (e.g. tenant.example.com).';
            }
        }

        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors, 'values' => $this->buildFormValues($input)];
        }

        try {
            $creatorUserId = \Auth::id();
            if ($creatorUserId === null) {
                $errors['general'] = 'Unable to determine authenticated user.';
                return ['success' => false, 'errors' => $errors, 'values' => $this->buildFormValues($input)];
            }

            $branding = [
                'primary_color' => $primaryColor,
                'secondary_color' => $secondaryColor,
                'accent_color' => $accentColor,
                'custom_domain' => $customDomain,
            ];

            $this->organizationService->createOrganization($name, $creatorUserId, $branding);
            return ['success' => true, 'errors' => [], 'values' => $this->buildFormValues([])];
        } catch (Throwable $throwable) {
            $errors['general'] = $throwable->getMessage();
        }

        return ['success' => false, 'errors' => $errors, 'values' => $this->buildFormValues($input)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function buildFormValues(array $input): array
    {
        return [
            'name' => (string) ($input['name'] ?? ''),
            'primary_color' => (string) ($input['primary_color'] ?? '#0066CC'),
            'secondary_color' => (string) ($input['secondary_color'] ?? '#F8F9FA'),
            'accent_color' => (string) ($input['accent_color'] ?? '#DC3545'),
            'custom_domain' => (string) ($input['custom_domain'] ?? ''),
        ];
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function validateColor(mixed $value, string $fallback): array
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return [$fallback, null];
        }

        $normalized = RequestValidator::hexColor($raw);
        if ($normalized === null) {
            return [$fallback, 'Use a valid 6-digit hex color (e.g. #AABBCC).'];
        }

        return [$normalized, null];
    }
}
