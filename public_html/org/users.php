<?php

declare(strict_types=1);

require __DIR__ . '/../../app/includes/bootstrap.php';

use App\Controllers\OrganizationUsersController;
use App\Repositories\OrganizationRepository;
use App\Repositories\UserRepository;
use App\Services\OrganizationService;

$connection = Database::connection();
$organizationRepository = new OrganizationRepository($connection);
$userRepository = new UserRepository($connection);
$service = new OrganizationService($organizationRepository, $userRepository);
$controller = new OrganizationUsersController($service, $userRepository);

$requestedOrgId = $_GET['id'] ?? null;
$domainOrg = resolve_organization_from_request();

if ($requestedOrgId === null && $domainOrg === null) {
    require view_path('org/not-found.php');
    return;
}

$controller->index($requestedOrgId ?? $domainOrg->id);
