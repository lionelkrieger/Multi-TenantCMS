<?php

declare(strict_types=1);

require __DIR__ . '/../../../app/includes/bootstrap.php';

use App\Models\User;
use App\Repositories\ExtensionRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\UserRepository;
use App\Services\ExtensionSettingsService;

header('Content-Type: application/json');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    return;
}

$connection = Database::connection();
$users = new UserRepository($connection);
$organizations = new OrganizationRepository($connection);
$extensions = new ExtensionRepository($connection);
$settings = new ExtensionSettingsService($connection);

$user = $users->findById(Auth::id() ?? '');
if ($user === null) {
    http_response_code(401);
    echo json_encode(['error' => 'User session expired.']);
    return;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$input = $method === 'GET' ? $_GET : getPayload();

$organizationId = isset($input['organization_id']) ? trim((string) $input['organization_id']) : '';
$extensionSlug = isset($input['extension_slug']) ? trim((string) $input['extension_slug']) : '';

if ($organizationId === '' || $extensionSlug === '') {
    http_response_code(422);
    echo json_encode(['error' => 'organization_id and extension_slug are required.']);
    return;
}

$organization = $organizations->findById($organizationId);
if ($organization === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Organization not found.']);
    return;
}

$extension = $extensions->findBySlug($extensionSlug);
if ($extension === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Extension not found.']);
    return;
}

if (!isAuthorizedForOrg($user, $organizationId)) {
    http_response_code(403);
    echo json_encode(['error' => 'You are not allowed to manage this organization.']);
    return;
}

switch ($method) {
    case 'GET':
        $status = $settings->status($extensionSlug, $organizationId);
        echo json_encode([
            'extension' => $extension->slug,
            'enabled' => $status['enabled'],
            'settings' => $status['settings'],
        ]);
        break;
    case 'POST':
    case 'PUT':
    case 'PATCH':
        $csrfToken = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!CSRF::validate((string) $csrfToken)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid CSRF token.']);
            return;
        }

        $changes = ['enabled' => false, 'settings' => []];

        if (array_key_exists('enabled', $input)) {
            $enableValue = filter_var($input['enabled'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($enableValue === null) {
                http_response_code(422);
                echo json_encode(['error' => 'Enabled must be a boolean value.']);
                return;
            }

            $canToggle = $extension->allowOrgToggle || $user->userType === 'master_admin';
            if (!$canToggle) {
                http_response_code(403);
                echo json_encode(['error' => 'Org admins cannot toggle this extension.']);
                return;
            }

            $settings->setEnabled($extensionSlug, $organizationId, $enableValue);
            $changes['enabled'] = true;

            \audit_log('extensions.org.toggle', [
                'actor_id' => $user->id,
                'organization_id' => $organizationId,
                'extension_slug' => $extensionSlug,
                'enabled' => $enableValue,
                'context' => 'api',
            ]);
        }

        if (!empty($input['settings']) && is_array($input['settings'])) {
            $secureKeys = [];
            if (!empty($input['secure_keys']) && is_array($input['secure_keys'])) {
                $secureKeys = array_map(static fn ($value): string => (string) $value, $input['secure_keys']);
            }

            $auditSettings = [];
            foreach ($input['settings'] as $key => $value) {
                if (!is_string($key) || $key === '') {
                    continue;
                }

                if ($key === ExtensionSettingsService::ENABLED_KEY) {
                    continue;
                }

                $encrypt = in_array($key, $secureKeys, true);
                $settings->set($extensionSlug, $organizationId, $key, $value, $encrypt);
                $auditSettings[$key] = $encrypt ? '[secure]' : $value;
            }

            $changes['settings'] = array_keys($input['settings']);

            if ($auditSettings !== []) {
                \audit_log('extensions.settings.updated', [
                    'actor_id' => $user->id,
                    'organization_id' => $organizationId,
                    'extension_slug' => $extensionSlug,
                    'settings' => $auditSettings,
                    'context' => 'api',
                ]);
            }
        }

        $status = $settings->status($extensionSlug, $organizationId);
        echo json_encode([
            'extension' => $extension->slug,
            'enabled' => $status['enabled'],
            'settings' => $status['settings'],
            'changes' => $changes,
        ]);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        break;
}

/**
 * @return array<string, mixed>
 */
function getPayload(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return $_POST;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $_POST;
}

function isAuthorizedForOrg(User $user, string $organizationId): bool
{
    if ($user->userType === 'master_admin') {
        return true;
    }

    return $user->userType === 'org_admin' && $user->organizationId === $organizationId;
}
