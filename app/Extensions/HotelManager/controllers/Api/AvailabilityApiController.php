<?php
// controllers/Api/AvailabilityApiController.php
declare(strict_types=1);

namespace App\Extensions\HotelManager\controllers\Api;

use App\Extensions\ExtensionContext;
use App\Extensions\HotelManager\Services\AvailabilityService;
use App\Extensions\HotelManager\Repositories\InventoryRepository;

class AvailabilityApiController
{
    private ExtensionContext $context;
    private AvailabilityService $availabilityService;

    public function __construct(ExtensionContext $context)
    {
        $this->context = $context;
        // Fetch the service from the registry as defined in bootstrap.php
        $this->availabilityService = $context->registry->getService('hotel.availability');
    }

    /**
     * API endpoint: GET /api/hotel/availability
     * Query parameters: room_type_id, check_in_date, check_out_date
     * Response: JSON with available count.
     */
    public function checkAvailability(): void
    {
        $input = $this->parseInput();

        $roomTypeId = $input['room_type_id'] ?? null;
        $checkInDate = $input['check_in_date'] ?? null;
        $checkOutDate = $input['check_out_date'] ?? null;

        if (!$roomTypeId || !$checkInDate || !$checkOutDate) {
            $this->sendJsonResponse(['error' => 'Missing required parameters: room_type_id, check_in_date, check_out_date'], 400);
            return;
        }

        // Validate date format (basic check)
        if (!strtotime($checkInDate) || !strtotime($checkOutDate)) {
            $this->sendJsonResponse(['error' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
            return;
        }

        // Validate date range (check-out should be after check-in)
        if (strtotime($checkOutDate) <= strtotime($checkInDate)) {
            $this->sendJsonResponse(['error' => 'Check-out date must be after check-in date.'], 400);
            return;
        }

        try {
            $availableCount = $this->availabilityService->getAvailableCount($roomTypeId, $checkInDate, $checkOutDate);

            $this->sendJsonResponse([
                'room_type_id' => $roomTypeId,
                'check_in_date' => $checkInDate,
                'check_out_date' => $checkOutDate,
                'available_count' => $availableCount
            ], 200);

        } catch (\Exception $e) {
            $this->context->logger->error('Error checking availability: ' . $e->getMessage(), [
                'room_type_id' => $roomTypeId,
                'check_in_date' => $checkInDate,
                'check_out_date' => $checkOutDate
            ]);
            $this->sendJsonResponse(['error' => 'Internal server error while checking availability.'], 500);
        }
    }

    private function parseInput(): array
    {
        $input = $_GET; // For GET requests
        // If it were a POST request with JSON body, you'd use:
        // $input = json_decode(file_get_contents('php://input'), true) ?: [];
        return $input;
    }

    private function sendJsonResponse(array $data, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit; // Important to stop execution after sending response
    }
}