<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\ExtensionRepository;
use App\Repositories\OrganizationRepository;

final class ExtensionController extends AdminController
{
    public function __construct(
        private readonly ExtensionRepository $extensions,
        private readonly OrganizationRepository $organizations
    ) {
    }

    public function index(array $query, array $input): void
    {
        $this->ensureMasterAdmin();

        $flash = [
            'success' => null,
            'error' => null,
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->handlePost($input);
            if ($result['redirect'] !== null) {
                $this->redirect($result['redirect']);
            }

            if ($result['message'] !== null) {
                $flash[$result['type']] = $result['message'];
            }
        } else {
            if (isset($query['saved'])) {
                $flash['success'] = 'Extension policy updated.';
            }
        }

        $extensions = $this->extensions->listAll();
        $enabledCounts = $this->extensions->enabledCounts();
        $totalOrganizations = $this->organizations->countAll();

        $this->render('admin/extensions', [
            'extensions' => $extensions,
            'enabledCounts' => $enabledCounts,
            'totalOrganizations' => $totalOrganizations,
            'flash' => $flash,
            'csrfToken' => \CSRF::token(),
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{redirect: ?string, type: 'success'|'error', message: ?string}
     */
    private function handlePost(array $input): array
    {
        if (!\CSRF::validate($input['csrf_token'] ?? '')) {
            return [
                'redirect' => null,
                'type' => 'error',
                'message' => 'Invalid security token. Refresh the page and try again.',
            ];
        }

        $action = isset($input['action']) ? trim((string) $input['action']) : '';
        return match ($action) {
            'org_toggle_policy' => $this->handleOrgTogglePolicy($input),
            default => [
                'redirect' => null,
                'type' => 'error',
                'message' => 'Unknown action submitted.',
            ],
        };
    }

    /**
     * @param array<string, mixed> $input
     * @return array{redirect: ?string, type: 'success'|'error', message: ?string}
     */
    private function handleOrgTogglePolicy(array $input): array
    {
        $extensionId = isset($input['extension_id']) ? trim((string) $input['extension_id']) : '';
        if (!\Validator::uuid($extensionId)) {
            return [
                'redirect' => null,
                'type' => 'error',
                'message' => 'Invalid extension identifier provided.',
            ];
        }

        $extension = $this->extensions->findById($extensionId);
        if ($extension === null) {
            return [
                'redirect' => null,
                'type' => 'error',
                'message' => 'Extension not found. Sync manifests and try again.',
            ];
        }

        $allow = isset($input['allow_org_toggle']) && (string) $input['allow_org_toggle'] === '1';
        $this->extensions->updateAllowOrgToggle($extension->id, $allow);

        return [
            'redirect' => '/admin/extensions.php?saved=1',
            'type' => 'success',
            'message' => null,
        ];
    }
}
