<?php
// src/Services/PosService.php
declare(strict_types=1);

namespace App\Extensions\HotelManager\Services;

use App\Extensions\HotelManager\Repositories\PosRepository;
use App\Extensions\HotelManager\Repositories\ReservationRepository;
use Psr\Log\LoggerInterface;

class PosService
{
    private PosRepository $posRepo;
    private ReservationRepository $reservationRepo;
    private LoggerInterface $logger;

    public function __construct(PosRepository $posRepo, ReservationRepository $reservationRepo, LoggerInterface $logger)
    {
        $this->posRepo = $posRepo;
        $this->reservationRepo = $reservationRepo;
        $this->logger = $logger;
    }

    public function createCategory(array $data): string
    {
        return $this->posRepo->createCategory($data);
    }

    public function createItem(array $data): string
    {
        return $this->posRepo->createItem($data);
    }

    public function addChargeToReservation(string $reservationId, string $itemId, int $quantity, string $chargedByUserId, string $notes = ''): string
    {
        // Fetch reservation to ensure it's valid and get org_id
        $reservation = $this->reservationRepo->findById($reservationId);
        if (!$reservation) {
            throw new \InvalidArgumentException("Reservation {$reservationId} not found.");
        }

        $item = $this->posRepo->findItemById($itemId);
        if (!$item) {
            throw new \InvalidArgumentException("POS Item {$itemId} not found.");
        }

        $totalAmount = $item['price'] * $quantity;

        $chargeData = [
            'organization_id' => $reservation['organization_id'], // Ensure charge belongs to same org as reservation
            'reservation_id' => $reservationId,
            'item_id' => $itemId,
            'category_name' => $item['category_name'], // Snapshot category name for reporting
            'quantity' => $quantity,
            'unit_price' => $item['price'], // Snapshot price at time of charge
            'total_amount' => $totalAmount,
            'charged_by_user_id' => $chargedByUserId,
            'notes' => $notes,
        ];

        $chargeId = $this->posRepo->createCharge($chargeData);

        $this->logger->info("POS charge added to reservation.", ['reservation_id' => $reservationId, 'charge_id' => $chargeId, 'amount' => $totalAmount]);

        return $chargeId;
    }

    // Add other methods like getChargesForReservation, voidCharge, etc.
}