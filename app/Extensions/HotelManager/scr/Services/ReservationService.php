<?php
// src/Services/ReservationService.php
declare(strict_types=1);

namespace App\Extensions\HotelManager\Services;

use App\Extensions\HotelManager\Repositories\ReservationRepository;
use App\Extensions\HotelManager\Interfaces\CrmIntegrationInterface; // Assume this interface exists
use Psr\Log\LoggerInterface; // Assume PSR-3 logger

class ReservationService
{
    private ReservationRepository $reservationRepo;
    private ?CrmIntegrationInterface $crmService; // CRM service is injected
    private LoggerInterface $logger;

    public function __construct(ReservationRepository $reservationRepo, ?CrmIntegrationInterface $crmService, LoggerInterface $logger)
    {
        $this->reservationRepo = $reservationRepo;
        $this->crmService = $crmService; // Can be null if CRM extension not active
        $this->logger = $logger;
    }

    public function createReservation(array $data): string
    {
        // Fetch guest profile from CRM if service is available
        $guestId = $data['guest_id'];
        $guestProfile = null;
        $loyaltyDiscount = 0.0;

        if ($this->crmService) {
            try {
                $guestProfile = $this->crmService->getGuestProfile($guestId);
                $loyaltyTier = $this->crmService->getGuestLoyaltyTier($guestId);
                // Apply discount based on tier - logic would be in PricingService
                // For now, just fetch the tier info
                $this->logger->debug("Fetched guest profile from CRM for reservation creation.", ['guest_id' => $guestId, 'loyalty_tier' => $loyaltyTier]);
            } catch (\Exception $e) {
                $this->logger->warning("Could not fetch guest profile from CRM: " . $e->getMessage(), ['guest_id' => $guestId]);
                // Continue with reservation, maybe without loyalty discount
            }
        }

        // Calculate final amount (this might be delegated to PricingService later)
        $finalAmount = $data['base_amount'] - ($data['discount_amount'] ?? 0);

        $reservationData = [
            'organization_id' => $data['organization_id'],
            'property_id' => $data['property_id'],
            'guest_id' => $guestId,
            'room_type_id' => $data['room_type_id'],
            'check_in_date' => $data['check_in_date'],
            'check_out_date' => $data['check_out_date'],
            'num_adults' => $data['num_adults'] ?? 1,
            'num_children' => $data['num_children'] ?? 0,
            'base_amount' => $data['base_amount'],
            'discount_amount' => $data['discount_amount'] ?? 0,
            'final_amount' => $finalAmount,
            'payment_method' => $data['payment_method'] ?? 'pay_on_arrival',
            'status' => 'confirmed', // Default status
            // ... other fields
        ];

        $reservationId = $this->reservationRepo->create($reservationData);

        // Update CRM with transaction (if service available)
        if ($this->crmService) {
            try {
                $this->crmService->addGuestTransaction($guestId, [
                    'type' => 'reservation',
                    'reservation_id' => $reservationId,
                    'amount' => $finalAmount,
                    'date' => date('Y-m-d H:i:s')
                ]);
                $this->crmService->updateGuestLtv($guestId, $finalAmount);
            } catch (\Exception $e) {
                 $this->logger->warning("Could not update CRM with reservation transaction: " . $e->getMessage(), ['reservation_id' => $reservationId, 'guest_id' => $guestId]);
            }
        }


        return $reservationId;
    }

    public function getReservationById(string $id): ?array
    {
        return $this->reservationRepo->findById($id);
    }

    // Add other methods like updateReservationStatus, etc.
}