<?php
declare(strict_types=1);

require __DIR__ . '/../../app/includes/bootstrap.php';

use App\Controllers\Admin\OrganizationController;
use App\Repositories\OrganizationRepository;
use App\Repositories\UserRepository;
use App\Services\OrganizationService;

$connection = Database::connection();
$organizationRepository = new OrganizationRepository($connection);
$userRepository = new UserRepository($connection);
$organizationService = new OrganizationService($organizationRepository, $userRepository);

$controller = new OrganizationController($organizationRepository, $organizationService, $userRepository);
$controller->index($_GET, $_POST);
