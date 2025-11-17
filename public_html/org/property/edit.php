<?php

declare(strict_types=1);

require __DIR__ . '/../../../app/includes/bootstrap.php';

use App\Controllers\PropertyController;
use App\Repositories\OrganizationRepository;
use App\Repositories\PropertyRepository;
use App\Services\PropertyService;

$connection = Database::connection();
$service = new PropertyService(
    new PropertyRepository($connection),
    new OrganizationRepository($connection)
);
$controller = new PropertyController($service);

$requestedOrgId = $_GET['id'] ?? $_POST['organization_id'] ?? null;
$domainOrg = resolve_organization_from_request();

if ($requestedOrgId === null && $domainOrg === null) {
    require view_path('org/not-found.php');
    return;
}

$propertyId = isset($_GET['property']) ? (string) $_GET['property'] : null;
if ($propertyId === null) {
    require view_path('public/property/not-found.php');
    return;
}

$organizationId = $requestedOrgId ?? $domainOrg->id;
$controller->edit($organizationId, $propertyId, $_POST);
