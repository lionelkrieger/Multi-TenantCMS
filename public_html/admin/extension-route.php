<?php

declare(strict_types=1);

require __DIR__ . '/../../app/includes/bootstrap.php';

use App\Extensions\Contracts\ExtensionRegistryInterface;
use App\Extensions\Exceptions\ExtensionException;
use App\Extensions\Routing\ExtensionRouteDispatcher;
use App\Services\ExtensionSettingsService;
use App\Support\CapabilityAuthorizer;

if (!Auth::check()) {
    redirect('/login.php');
}

$extensionSlug = $_GET['extension'] ?? '';
$surfacePath = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($extensionSlug === '' || $surfacePath === '') {
    http_response_code(404);
    require view_path('errors/not-found.php');
    return;
}

$registry = app(ExtensionRegistryInterface::class);
$settings = new ExtensionSettingsService(Database::connection());
$dispatcher = new ExtensionRouteDispatcher($registry, $settings, new CapabilityAuthorizer());

try {
    $dispatcher->dispatch('admin', $extensionSlug, $method, $surfacePath);
} catch (ExtensionException $exception) {
    http_response_code(500);
    app_logger()->error('Admin extension route failed', [
        'extension_slug' => $extensionSlug,
        'path' => $surfacePath,
        'error' => $exception->getMessage(),
    ]);
    require view_path('errors/500.php');
}
