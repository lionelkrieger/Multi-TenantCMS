<?php
// controllers/HotelManagerController.php
declare(strict_types=1);

namespace App\Extensions\HotelManager\controllers;

use App\Extensions\ExtensionContext;

class HotelManagerController
{
    private ExtensionContext $context;

    public function __construct(ExtensionContext $context)
    {
        $this->context = $context;
    }

    public function index(): string
    {
        // Fetch summary data using services
        $reservationService = $this->context->registry->getService('hotel.reservation');
        $inventoryService = $this->context->registry->getService('hotel.inventory');
        $posService = $this->context->registry->getService('hotel.pos');

        // Example data fetch - implement actual logic
        $todayCheckIns = 12; // $reservationService->getTodaysCheckIns();
        $todayRevenue = 24500.00; // $reservationService->getTodaysRevenue();
        $totalRooms = 50; // $inventoryService->getTotalRooms();
        $occupiedRooms = 42; // $reservationService->getOccupiedRoomCount();

        // Render the dashboard view
        ob_start();
        include __DIR__ . '/../views/dashboard/index.php';
        return ob_get_clean();
    }

    public function settings(): string
    {
        // Render settings view
        ob_start();
        include __DIR__ . '/../views/settings/hotel_settings.php';
        return ob_get_clean();
    }
}