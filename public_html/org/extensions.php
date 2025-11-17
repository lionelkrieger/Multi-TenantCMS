<?php

declare(strict_types=1);

require __DIR__ . '/../../app/includes/bootstrap.php';

use App\Controllers\OrganizationExtensionsController;
use App\Extensions\ExtensionRegistry;
use App\Extensions\ManifestValidator;
use App\Repositories\ExtensionRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\UserRepository;
use App\Services\ExtensionSettingsService;

$connection = Database::connection();
$settingsService = new ExtensionSettingsService($connection);
$registry = new ExtensionRegistry(new ManifestValidator(), $settingsService, $connection);

$controller = new OrganizationExtensionsController(
    new OrganizationRepository($connection),
    new UserRepository($connection),
    new ExtensionRepository($connection),
    $settingsService,
    $registry
);

$requestedOrgId = $_GET['id'] ?? null;
$domainOrg = resolve_organization_from_request();

if ($requestedOrgId === null && $domainOrg === null) {
    require view_path('org/not-found.php');
    return;
}

$controller->center($requestedOrgId ?? $domainOrg->id, $_GET, $_POST);
