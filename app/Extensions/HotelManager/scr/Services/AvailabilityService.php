<?php
// src/Services/AvailabilityService.php
declare(strict_types=1);

namespace App\Extensions\HotelManager\Services;

use App\Extensions\HotelManager\Repositories\InventoryRepository;
use App\Extensions\HotelManager\Repositories\ReservationRepository;

class AvailabilityService
{
    private InventoryRepository $inventoryRepo;
    private ReservationRepository $reservationRepo;

    public function __construct(InventoryRepository $inventoryRepo, ReservationRepository $reservationRepo)
    {
        $this->inventoryRepo = $inventoryRepo;
        $this->reservationRepo = $reservationRepo;
    }

    /**
     * Calculates available units for a given room type and date range.
     */
    public function getAvailableCount(string $roomTypeId, string $checkInDate, string $checkOutDate): int
    {
        $roomType = $this->inventoryRepo->findById($roomTypeId);
        if (!$roomType) {
            return 0; // Room type doesn't exist
        }

        $totalUnits = (int)$roomType['total_units'];
        if ($totalUnits <= 0) {
            return 0; // No physical units
        }

        $bookedCount = $this->reservationRepo->getOverlappingReservationCount($roomTypeId, $checkInDate, $checkOutDate);

        $availableCount = $totalUnits - $bookedCount;

        return max(0, $availableCount); // Ensure non-negative
    }
}