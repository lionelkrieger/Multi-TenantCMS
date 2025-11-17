<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Extensions\Contracts\ExtensionRegistryInterface;
use App\Extensions\Exceptions\ExtensionException;
use App\Models\User;
use App\Repositories\ExtensionRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\UserRepository;
use App\Services\ExtensionSettingsService;

final class OrganizationExtensionsController extends Controller
{
    public function __construct(
        private readonly OrganizationRepository $organizations,
        private readonly UserRepository $users,
        private readonly ExtensionRepository $extensions,
        private readonly ExtensionSettingsService $settings,
        private readonly ExtensionRegistryInterface $registry
    ) {
    }

    public function center(string $organizationId, array $query, array $input): void
    {
        $user = $this->authorize($organizationId);
        $organization = $this->organizations->findById($organizationId);
        if ($organization === null) {
            $this->render('org/not-found', ['message' => 'Organization not found.']);
            return;
        }

        $flash = [
            'success' => null,
            'error' => null,
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->handlePost($organizationId, $input, $user);
            if ($result['redirect'] !== null) {
                $this->redirect($result['redirect']);
            }

            if ($result['message'] !== null) {
                $flash[$result['type']] = $result['message'];
            }
        } else {
            if (isset($query['saved'])) {
                $flash['success'] = 'Extension settings updated.';
            }
        }

        $extensionRows = [];
        foreach ($this->extensions->listAll() as $extension) {
            $status = $this->settings->status($extension->slug, $organizationId);
            $extensionRows[] = [
                'extension' => $extension,
                'enabled' => $status['enabled'],
                'settings' => $status['settings'],
                'canToggle' => $extension->allowOrgToggle || $user->userType === 'master_admin',
            ];
        }

        $this->render('org/extensions', [
            'organization' => $organization,
            'extensions' => $extensionRows,
            'flash' => $flash,
            'csrfToken' => \CSRF::token(),
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{redirect: ?string, type: 'success'|'error', message: ?string}
     */
    private function handlePost(string $organizationId, array $input, User $actor): array
    {
        if (!\CSRF::validate($input['csrf_token'] ?? '')) {
            return [
                'redirect' => null,
                'type' => 'error',
                'message' => 'Invalid security token. Refresh and try again.',
            ];
        }

        $action = isset($input['action']) ? trim((string) $input['action']) : '';
        return match ($action) {
            'toggle' => $this->handleToggle($organizationId, $input, $actor),
            'update_settings' => $this->handleSettingsUpdate($organizationId, $input, $actor),
            default => [
                'redirect' => null,
                'type' => 'error',
                'message' => 'Unknown action requested.',
            ],
        };
    }

    /**
     * @param array<string, mixed> $input
     * @return array{redirect: ?string, type: 'success'|'error', message: ?string}
     */
    private function handleToggle(string $organizationId, array $input, User $actor): array
    {
        $slug = isset($input['extension_slug']) ? trim((string) $input['extension_slug']) : '';
        if ($slug === '') {
            return [
                'redirect' => null,
                'type' => 'error',
                'message' => 'Missing extension identifier.',
            ];
        }

        $extension = $this->extensions->findBySlug($slug);
        if ($extension === null) {
            return [
                'redirect' => null,
                'type' => 'error',
                'message' => 'Extension not found. Contact support.',
            ];
        }

        $canToggle = $extension->allowOrgToggle || $actor->userType === 'master_admin';
        if (!$canToggle) {
            return [
                'redirect' => null,
                'type' => 'error',
                'message' => 'This extension can only be managed by the platform team.',
            ];
        }

        $enabled = isset($input['enabled']) && (string) $input['enabled'] === '1';

        try {
            if ($enabled) {
                $this->registry->activate($extension->slug, $organizationId);
            } else {
                $this->registry->deactivate($extension->slug, $organizationId);
            }
        } catch (ExtensionException $exception) {
            return [
                'redirect' => null,
                'type' => 'error',
                'message' => $exception->getMessage(),
            ];
        }

        \audit_log('extensions.org.toggle', [
            'actor_id' => $actor->id,
            'organization_id' => $organizationId,
            'extension_slug' => $extension->slug,
            'enabled' => $enabled,
        ]);

        return [
            'redirect' => '/org/extensions.php?saved=1&id=' . urlencode($organizationId),
            'type' => 'success',
            'message' => null,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{redirect: ?string, type: 'success'|'error', message: ?string}
     */
    private function handleSettingsUpdate(string $organizationId, array $input, User $actor): array
    {
        $slug = isset($input['extension_slug']) ? trim((string) $input['extension_slug']) : '';
        if ($slug === '') {
            return [
                'redirect' => null,
                'type' => 'error',
                'message' => 'Missing extension identifier.',
            ];
        }

        $extension = $this->extensions->findBySlug($slug);
        if ($extension === null) {
            return [
                'redirect' => null,
                'type' => 'error',
                'message' => 'Extension not found. Contact support.',
            ];
        }

        $canManage = $extension->allowOrgToggle || $actor->userType === 'master_admin';
        if (!$canManage) {
            return [
                'redirect' => null,
                'type' => 'error',
                'message' => 'This extension is centrally managed. Reach out to the platform team for changes.',
            ];
        }

        return match ($slug) {
            'platform/payfast' => $this->updatePayFastSettings($organizationId, $input, $actor),
            'platform/gtm' => $this->updateGtmSettings($organizationId, $input, $actor),
            default => [
                'redirect' => null,
                'type' => 'error',
                'message' => 'This extension does not expose configurable settings yet.',
            ],
        };
    }

    /**
     * @param array<string, mixed> $input
     * @return array{redirect: ?string, type: 'success'|'error', message: ?string}
     */
    private function updatePayFastSettings(string $organizationId, array $input, User $actor): array
    {
        $merchantId = isset($input['merchant_id']) ? trim((string) $input['merchant_id']) : '';
        $merchantKey = isset($input['merchant_key']) ? trim((string) $input['merchant_key']) : '';
        $sandboxMode = isset($input['sandbox_mode']) && (string) $input['sandbox_mode'] === '1';

        if ($merchantId === '') {
            return [
                'redirect' => null,
                'type' => 'error',
                'message' => 'Merchant ID is required.',
            ];
        }

        if (!preg_match('/^[A-Za-z0-9_-]{5,50}$/', $merchantId)) {
            return [
                'redirect' => null,
                'type' => 'error',
                'message' => 'Merchant ID must be alphanumeric (5-50 characters).',
            ];
        }

        $this->settings->set('platform/payfast', $organizationId, 'merchant_id', $merchantId);
        if ($merchantKey !== '') {
            $this->settings->set('platform/payfast', $organizationId, 'merchant_key', $merchantKey, true);
        }
        $this->settings->set('platform/payfast', $organizationId, 'sandbox_mode', $sandboxMode);

        \audit_log('extensions.settings.updated', [
            'actor_id' => $actor->id,
            'organization_id' => $organizationId,
            'extension_slug' => 'platform/payfast',
            'settings' => [
                'merchant_id' => $merchantId,
                'merchant_key_rotated' => $merchantKey !== '',
                'sandbox_mode' => $sandboxMode,
            ],
        ]);

        return [
            'redirect' => '/org/extensions.php?saved=1&id=' . urlencode($organizationId),
            'type' => 'success',
            'message' => null,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{redirect: ?string, type: 'success'|'error', message: ?string}
     */
    private function updateGtmSettings(string $organizationId, array $input, User $actor): array
    {
        $containerId = isset($input['container_id']) ? trim((string) $input['container_id']) : '';
        if ($containerId === '') {
            return [
                'redirect' => null,
                'type' => 'error',
                'message' => 'GTM container ID is required.',
            ];
        }

        if (!preg_match('/^GTM-[A-Z0-9]{7}$/', strtoupper($containerId))) {
            return [
                'redirect' => null,
                'type' => 'error',
                'message' => 'Container ID must follow the GTM-XXXXXXX format.',
            ];
        }

        $enhanced = isset($input['enhanced_conversions']) && (string) $input['enhanced_conversions'] === '1';
        $this->settings->set('platform/gtm', $organizationId, 'container_id', strtoupper($containerId));
        $this->settings->set('platform/gtm', $organizationId, 'enhanced_conversions', $enhanced);

        \audit_log('extensions.settings.updated', [
            'actor_id' => $actor->id,
            'organization_id' => $organizationId,
            'extension_slug' => 'platform/gtm',
            'settings' => [
                'container_id' => strtoupper($containerId),
                'enhanced_conversions' => $enhanced,
            ],
        ]);

        return [
            'redirect' => '/org/extensions.php?saved=1&id=' . urlencode($organizationId),
            'type' => 'success',
            'message' => null,
        ];
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

        if ($user->userType === 'org_admin' && $user->organizationId === $organizationId) {
            return $user;
        }

        http_response_code(403);
        $this->render('errors/forbidden');
        exit;
    }
}
