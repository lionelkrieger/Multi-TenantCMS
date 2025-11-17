<?php

declare(strict_types=1);

require __DIR__ . '/../app/includes/bootstrap.php';

use App\Controllers\DashboardController;
use App\Repositories\OrganizationRepository;
use App\Repositories\UserRepository;

$connection = Database::connection();
$controller = new DashboardController(
	new UserRepository($connection),
	new OrganizationRepository($connection)
);

$controller->index();
