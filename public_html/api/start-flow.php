<?php

declare(strict_types=1);

require __DIR__ . '/../../app/includes/bootstrap.php';

use App\Repositories\OrganizationRepository;
use App\Repositories\PropertyRepository;
use App\Repositories\UserFlowRepository;
use App\Services\UserFlowService;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    return;
}

$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }
}

$csrfToken = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!CSRF::validate((string) $csrfToken)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid CSRF token']);
    return;
}

$organizationId = trim((string) ($input['organization_id'] ?? $input['org'] ?? ''));
$propertyId = trim((string) ($input['property_id'] ?? $input['property'] ?? ''));
$message = trim((string) ($input['message'] ?? ''));

if ($organizationId === '' || $propertyId === '') {
    http_response_code(422);
    echo json_encode(['error' => 'organization_id and property_id are required.']);
    return;
}

try {
    $connection = Database::connection();
    $organizationRepository = new OrganizationRepository($connection);
    $propertyRepository = new PropertyRepository($connection);
    $service = new UserFlowService(
        new UserFlowRepository($connection),
        $organizationRepository,
        $propertyRepository
    );

    $flow = $service->startFlow($organizationId, $propertyId, \Auth::id(), $message);

    echo json_encode([
        'ok' => true,
        'flow_token' => $flow->token,
    ]);
} catch (RuntimeException $exception) {
    http_response_code(404);
    echo json_encode(['error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    logger('Failed to start user flow', ['error' => $exception->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Unable to start flow.']);
}
