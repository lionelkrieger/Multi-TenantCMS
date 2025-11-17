<?php
// bootstrap.php
declare(strict_types=1);

use App\Extensions\ExtensionContext;
use App\Extensions\ExtensionRegistry;

return static function (ExtensionContext $context, ExtensionRegistry $registry): void {
    // Register services
    $registry->registerService('hotel.inventory', function () use ($context) {
        return new App\Extensions\HotelManager\Services\InventoryService(
            new App\Extensions\HotelManager\Repositories\InventoryRepository($context->connection),
            new App\Extensions\HotelManager\Repositories\HotelPropertyRepository($context->connection)
        );
    });

    $registry->registerService('hotel.reservation', function () use ($context) {
        // Assume CRM integration is provided by the core system or another extension
        // For now, we'll pass a dummy/null object or handle the dependency differently if needed later
        $crmService = $registry->getService('crm.integration'); // This service needs to be registered by the CRM extension
        return new App\Extensions\HotelManager\Services\ReservationService(
            new App\Extensions\HotelManager\Repositories\ReservationRepository($context->connection),
            $crmService, // Inject CRM service
            $context->logger
        );
    });

    $registry->registerService('hotel.pos', function () use ($context) {
        return new App\Extensions\HotelManager\Services\PosService(
            new App\Extensions\HotelManager\Repositories\PosRepository($context->connection),
            new App\Extensions\HotelManager\Repositories\ReservationRepository($context->connection),
            $context->logger
        );
    });

    $registry->registerService('hotel.availability', function () use ($context) {
        return new App\Extensions\HotelManager\Services\AvailabilityService(
            new App\Extensions\HotelManager\Repositories\InventoryRepository($context->connection),
            new App\Extensions\HotelManager\Repositories\ReservationRepository($context->connection)
        );
    });

    $registry->registerService('hotel.pricing', function () use ($context) {
        // Assume CRM service for loyalty is provided
        $crmService = $registry->getService('crm.integration');
        return new App\Extensions\HotelManager\Services\PricingService($crmService);
    });

    // Register controllers (example route registration - core system handles this)
    // The core system should map routes defined in extension.json to these controllers
    // $registry->registerController('hotel.manager', App\Extensions\HotelManager\controllers\HotelManagerController::class);
    // $registry->registerController('hotel.reservation', App\Extensions\HotelManager\controllers\ReservationController::class);
    // ... etc

    // Register event listeners if needed
    // $registry->addEventListener('reservation.created', function($event) use ($registry) {
    //     // Do something when a reservation is created via the unified system
    // });

    $context->logger->info('Hotel Manager extension loaded with services.');
};