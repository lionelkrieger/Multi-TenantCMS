<?php
declare(strict_types=1);

require __DIR__ . '/../app/includes/bootstrap.php';

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
$term = isset($_GET['q']) ? (string) $_GET['q'] : null;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;

$controller->search($organizationId, $term, $page);
