<?php
declare(strict_types=1);

require __DIR__ . '/../app/includes/bootstrap.php';

use App\Controllers\AuthController;
use App\Repositories\UserRepository;
use App\Services\AuthService;

$connection = Database::connection();
$authService = new AuthService(new UserRepository($connection));
$controller = new AuthController($authService);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$controller->login($_POST);
} else {
	$controller->showLogin();
}
