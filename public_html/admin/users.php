<?php
declare(strict_types=1);

require __DIR__ . '/../../app/includes/bootstrap.php';

use App\Controllers\Admin\UserController;
use App\Repositories\OrganizationRepository;
use App\Repositories\UserInviteRepository;
use App\Repositories\UserRepository;
use App\Services\UserInviteService;

$connection = Database::connection();
$userRepository = new UserRepository($connection);
$organizationRepository = new OrganizationRepository($connection);
$userInviteRepository = new UserInviteRepository($connection);
$userInviteService = new UserInviteService($userInviteRepository, $userRepository);

$controller = new UserController($userRepository, $userInviteRepository, $userInviteService, $organizationRepository);
$controller->index($_GET, $_POST);
