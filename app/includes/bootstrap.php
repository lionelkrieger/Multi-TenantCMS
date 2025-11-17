<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$composerAutoload = base_path('vendor/autoload.php');
if (file_exists($composerAutoload)) {
	require_once $composerAutoload;
}

spl_autoload_register(static function (string $class): void {
	$prefix = 'App\\';
	if (str_starts_with($class, $prefix)) {
		$relative = substr($class, strlen($prefix));
		$path = app_path(str_replace('\\', '/', $relative) . '.php');
		if (file_exists($path)) {
			require_once $path;
		}
	}
});

require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/csrftoken.php';
require_once __DIR__ . '/domain_routing.php';

$appConfigPath = config_path('app.php');
if (file_exists($appConfigPath)) {
	$appConfig = require $appConfigPath;
	if (isset($appConfig['timezone'])) {
		date_default_timezone_set($appConfig['timezone']);
	}
}

$errorHandler = new App\Support\ErrorHandler(app_logger());
$errorHandler->register();
