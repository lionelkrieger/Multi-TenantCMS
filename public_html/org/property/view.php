<?php

declare(strict_types=1);

require __DIR__ . '/../../../app/includes/bootstrap.php';

use App\Controllers\PublicPropertyController;
use App\Repositories\OrganizationRepository;
use App\Repositories\PropertyRepository;
use App\Services\PropertyService;

$connection = Database::connection();
$organizationRepository = new OrganizationRepository($connection);
$propertyRepository = new PropertyRepository($connection);
$propertyService = new PropertyService($propertyRepository, $organizationRepository);
$controller = new PublicPropertyController($propertyService, $organizationRepository);

$organizationId = isset($_GET['org']) ? (string) $_GET['org'] : null;
$propertyId = isset($_GET['property']) ? (string) $_GET['property'] : null;

$controller->view($organizationId, $propertyId);
