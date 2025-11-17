<?php

declare(strict_types=1);

require __DIR__ . '/../../app/includes/bootstrap.php';

use App\Controllers\Admin\ExtensionController;
use App\Repositories\ExtensionRepository;
use App\Repositories\OrganizationRepository;

$connection = Database::connection();
$controller = new ExtensionController(
    new ExtensionRepository($connection),
    new OrganizationRepository($connection)
);

$controller->index($_GET, $_POST);
