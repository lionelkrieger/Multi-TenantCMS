<?php
declare(strict_types=1);

require __DIR__ . '/../../app/includes/bootstrap.php';

use App\Controllers\OrganizationController;
use App\Repositories\OrganizationRepository;
use App\Repositories\PropertyRepository;
use App\Repositories\UserInviteRepository;
use App\Repositories\UserRepository;
use App\Services\OrganizationService;
use App\Services\PropertyService;

$connection = Database::connection();
$organizationRepository = new OrganizationRepository($connection);
$userRepository = new UserRepository($connection);
$propertyRepository = new PropertyRepository($connection);
$inviteRepository = new UserInviteRepository($connection);

$organizationService = new OrganizationService($organizationRepository, $userRepository);
$propertyService = new PropertyService($propertyRepository, $organizationRepository);
$controller = new OrganizationController($organizationService, $propertyService, $userRepository, $inviteRepository);

$requestedOrgId = $_GET['id'] ?? null;
$domainOrg = resolve_organization_from_request();

if ($requestedOrgId === null && $domainOrg === null) {
	require view_path('org/not-found.php');
	return;
}

$controller->dashboard($requestedOrgId ?? $domainOrg->id);
