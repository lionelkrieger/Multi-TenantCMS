<?php
// controllers/Api/FolioApiController.php
declare(strict_types=1);

namespace App\Extensions\HotelManager\controllers\Api;

use App\Extensions\ExtensionContext;
use App\Extensions\HotelManager\Services\PosService; // PosService handles folio charges
use App\Extensions\HotelManager\Repositories\PosRepository;

class FolioApiController
{
    private ExtensionContext $context;
    private PosService $posService; // PosService manages charges, which are the folio items

    public function __construct(ExtensionContext $context)
    {
        $this->context = $context;
        $this->posService = $context->registry->getService('hotel.pos');
    }

    /**
     * API endpoint: GET /api/hotel/folio/{reservation_id}
     * Fetches all charges for a specific reservation.
     */
    public function getFolio(string $reservationId): void
    {
        // Optional: Add authentication/authorization check here if needed for API access
        // e.g., verify user has permission to view this reservation's folio

        try {
            // The PosRepository would need a method to get charges by reservation ID
            $posRepo = $this->context->registry->getService('pos.repository'); // Assuming a repository service exists or is injected into PosService
            // For now, let's assume PosService has a method or we get it directly:
            // This logic would typically reside in PosService or a FolioService if separate.
            // We'll assume PosRepository is accessible via the service or passed in.
            // Let's fetch using the repository directly from the service's internal state or a getter if available.
            // For this example, let's assume PosService has a method to get charges.
            // Since PosService was built with PosRepository, it likely has access to it internally.
            // We need to expose a method in PosService to get charges for a reservation.
            // This is a limitation of the previous service definition if not already present.
            // Let's assume PosService has a method like this:
            $charges = $this->posService->getChargesForReservation($reservationId); // This method needs to be implemented in PosService

            if ($charges === null) { // Or however the service indicates no folio/reservation found
                 $this->sendJsonResponse(['error' => 'Folio not found for reservation ID: ' . $reservationId], 404);
                 return;
            }

            $this->sendJsonResponse([
                'reservation_id' => $reservationId,
                'charges' => $charges
            ], 200);

        } catch (\Exception $e) {
            $this->context->logger->error('Error fetching folio: ' . $e->getMessage(), ['reservation_id' => $reservationId]);
            $this->sendJsonResponse(['error' => 'Internal server error while fetching folio.'], 500);
        }
    }

    /**
     * API endpoint: POST /api/hotel/folio/{reservation_id}/charge
     * Adds a new charge to the folio for a specific reservation.
     * Request body (JSON): { item_id, quantity, notes, charged_by_user_id }
     */
    public function addCharge(string $reservationId): void
    {
        $input = $this->parseInput();

        $itemId = $input['item_id'] ?? null;
        $quantity = (int)($input['quantity'] ?? 1); // Default to 1 if not provided or invalid
        $chargedByUserId = $input['charged_by_user_id'] ?? null; // Should come from authenticated user session in a real API
        $notes = $input['notes'] ?? '';

        if (!$itemId || !$chargedByUserId) {
            $this->sendJsonResponse(['error' => 'Missing required parameters: item_id, charged_by_user_id'], 400);
            return;
        }

        if ($quantity < 1) {
            $this->sendJsonResponse(['error' => 'Quantity must be at least 1.'], 400);
            return;
        }

        // In a real API, $chargedByUserId should be obtained from the authenticated user's session/token.
        // For this example, we assume it's provided and validated by a middleware or the core system before reaching here.
        // We'll proceed with the assumption that $chargedByUserId is valid.

        try {
            $chargeId = $this->posService->addChargeToReservation($reservationId, $itemId, $quantity, $chargedByUserId, $notes);

            // Optionally, fetch the newly created charge details to return
            // This would require the PosRepository to have a findById method for charges.
            $newCharge = $this->posService->getChargeById($chargeId); // This method also needs to be implemented.

            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Charge added successfully.',
                'charge_id' => $chargeId,
                'charge_details' => $newCharge // Include details if available
            ], 201); // 201 Created

        } catch (\InvalidArgumentException $e) {
             // Specific error from the service (e.g., reservation/item not found)
             $this->sendJsonResponse(['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            $this->context->logger->error('Error adding charge to folio: ' . $e->getMessage(), [
                'reservation_id' => $reservationId,
                'item_id' => $itemId,
                'quantity' => $quantity
            ]);
            $this->sendJsonResponse(['error' => 'Internal server error while adding charge.'], 500);
        }
    }

    private function parseInput(): array
    {
        // For POST/PUT requests with JSON body
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
            return is_array($input) ? $input : [];
        }
        // Fallback for form data or query string (though less common for POST APIs)
        return $_POST ?: [];
    }

    private function sendJsonResponse(array $data, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit; // Important to stop execution after sending response
    }
}