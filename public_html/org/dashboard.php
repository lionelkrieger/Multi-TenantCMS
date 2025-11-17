<?php
declare(strict_types=1);

require __DIR__ . '/../../app/includes/bootstrap.php';

use App\Controllers\OrganizationController;
use App\Repositories\OrganizationRepository;
use App\Repositories\PropertyRepository;
use App\Repositories\UserRepository;
use App\Services\OrganizationService;
use App\Services\PropertyService;

$connection = Database::connection();
$service = new OrganizationService(
	new OrganizationRepository($connection),
	new UserRepository($connection)
);
$propertyService = new PropertyService(
	new PropertyRepository($connection),
	new OrganizationRepository($connection)
);
$controller = new OrganizationController($service, $propertyService);

$requestedOrgId = $_GET['id'] ?? null;
$domainOrg = resolve_organization_from_request();

if ($requestedOrgId === null && $domainOrg === null) {
	require view_path('org/not-found.php');
	return;
}

$controller->dashboard($requestedOrgId ?? $domainOrg->id);
