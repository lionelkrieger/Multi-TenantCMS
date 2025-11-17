<?php
// src/PayFastIPNHandler.php
declare(strict_types=1);

namespace App\Extensions\PayFast;

use PDO;
use Psr\Log\LoggerInterface; // Assuming PSR-3 logger interface
use App\Extensions\PayFast\PayFastAPIClient;

class PayFastIPNHandler
{
    private PDO $pdo;
    private LoggerInterface $logger; // Injected logger

    public function __construct(PDO $connection, LoggerInterface $logger)
    {
        $this->pdo = $connection;
        $this->logger = $logger;
    }

    /**
     * Main handler for the IPN endpoint.
     * Reads $_POST, validates signature and merchant ID, then processes the payment status.
     */
    public function handleIPN(): void
    {
        $this->logger->debug('IPN Handler called.');

        // 1. Read POST data from PayFast
        $postData = $_POST; // PayFast sends data via POST

        if (empty($postData)) {
            $this->logger->warning('IPN received with empty POST data.');
            http_response_code(400);
            echo "Bad Request: Empty POST data.";
            return;
        }

        // 2. Log raw IPN data (carefully, do not log sensitive details like the signature itself in prod logs if not needed)
        $this->logger->debug('Raw IPN data received.', ['raw_post' => $postData]); // Be cautious with logging PII/financial data

        // 3. Verify the signature
        // We need the merchant ID first to get the correct key for verification.
        // PayFast usually sends 'merchant_id' in the IPN.
        $receivedMerchantId = $postData['merchant_id'] ?? '';

        if (empty($receivedMerchantId)) {
            $this->logger->warning('IPN received without merchant_id.');
            http_response_code(400);
            echo "Bad Request: Missing merchant_id.";
            return;
        }

        // To verify the signature, we need the *correct* merchant key for this specific merchant_id.
        // This requires looking up the organization that *owns* this merchant_id.
        $orgSettings = $this->getOrgSettingsByMerchantId($receivedMerchantId);

        if (!$orgSettings) {
            $this->logger->error("IPN received for unknown/unauthorized merchant_id: {$receivedMerchantId}");
            http_response_code(400);
            echo "Bad Request: Unknown merchant_id.";
            return;
        }

        // Initialize API client with the *specific* org's credentials for signature verification
        $apiClient = new PayFastAPIClient(
            $orgSettings['merchant_id'],
            $orgSettings['merchant_key'], // This must be the decrypted key
            $orgSettings['sandbox_mode'],
            // ExtensionContext might not be directly available here, depending on how logger/context is passed.
            // We might need to pass a simpler logger or just use $this->logger.
            // For now, we'll assume the API client doesn't need the full context for verification.
            // If it does, we might need to refactor or pass a minimal context/logger.
            // For simplicity in this handler, we'll just pass the logger if needed by API client, or make it optional for verifyIPNSignature.
             // Let's adjust the API client constructor or verifyIPNSignature to not require full context for signature verification.
             // We'll pass a logger stub or just log errors directly here if verification fails.
            (object)['logger' => $this->logger] // Minimal context-like object just for logging within APIClient if needed, or refactor APIClient.
        );


        if (!$apiClient->verifyIPNSignature($postData)) {
            $this->logger->error('IPN signature verification failed.');
            http_response_code(400);
            echo "Bad Request: Signature verification failed.";
            return;
        }

        // 4. Validate merchant ID (compare received vs configured for this org)
        if (!$apiClient->validateIPNMerchantId($receivedMerchantId)) {
            $this->logger->error('IPN merchant ID validation failed.');
            http_response_code(400);
            echo "Bad Request: Merchant ID validation failed.";
            return;
        }

        // 5. Process the IPN data
        $this->processIPNData($postData);

        // 6. Respond to PayFast (send 200 OK)
        http_response_code(200);
        echo "OK"; // PayFast expects "OK" on success
    }

    /**
     * Looks up organization settings based on the merchant_id received in the IPN.
     * This is crucial to ensure we're using the *right* credentials for verification and updates.
     */
    private function getOrgSettingsByMerchantId(string $merchantId): ?array
    {
        // Query organizations table for the one matching this merchant_id (live or test)
        // We need to check both payfast_merchant_id and payfast_test_merchant_id columns.
        $stmt = $this->pdo->prepare("
            SELECT id, payfast_enabled, payfast_merchant_id, payfast_merchant_key, payfast_sandbox_mode,
                   payfast_test_merchant_id, payfast_test_merchant_key
            FROM organizations
            WHERE (payfast_merchant_id = ? OR payfast_test_merchant_id = ?)
              AND payfast_enabled = TRUE
        ");
        $stmt->execute([$merchantId, $merchantId]);
        $org = $stmt->fetch();

        if (!$org) {
            return null; // Merchant ID not found or not enabled
        }

        // Determine which key to use based on the received ID
        $isSandbox = ($org['payfast_test_merchant_id'] === $merchantId);
        $actualMerchantId = $isSandbox ? $org['payfast_test_merchant_id'] : $org['payfast_merchant_id'];
        $encryptedKey = $isSandbox ? $org['payfast_test_merchant_key'] : $org['payfast_merchant_key'];

        // IMPORTANT: Decrypt the key
        try {
            $decryptedKey = $this->decryptMerchantKey($encryptedKey);
        } catch (\Exception $e) {
             $this->logger->error("Failed to decrypt PayFast merchant key for IPN processing: " . $e->getMessage());
             return null;
        }

        return [
            'org_id' => $org['id'],
            'merchant_id' => $actualMerchantId,
            'merchant_key' => $decryptedKey,
            'sandbox_mode' => $isSandbox,
        ];
    }


    /**
     * Processes the validated IPN data and updates the reservation.
     */
    private function processIPNData(array $ipnData): void
    {
        $reservationId = $ipnData['custom_str1'] ?? $ipnData['m_payment_id'] ?? null; // Prefer custom_str1 if set
        $pfPaymentId = $ipnData['pf_payment_id'] ?? null;
        $paymentStatus = $ipnData['payment_status'] ?? null;
        $amountGross = $ipnData['amount_gross'] ?? null;
        $amountFee = $ipnData['amount_fee'] ?? null;
        $amountNet = $ipnData['amount_net'] ?? null;

        if (empty($reservationId)) {
            $this->logger->error('IPN processed but no reservation_id found in custom_str1 or m_payment_id.', ['ipn_data' => $ipnData]);
            return; // Can't update a reservation without its ID
        }

        // Log the IPN data associated with the reservation
        $this->logger->info("Processing IPN for reservation {$reservationId}", [
            'pf_payment_id' => $pfPaymentId,
            'payment_status' => $paymentStatus,
            'gross' => $amountGross,
            'fee' => $amountFee,
            'net' => $amountNet,
        ]);

        // Check if payment was successful
        if ($paymentStatus === 'COMPLETE') {
            // Update reservation status to 'paid'
            $this->updateReservationToPaid($reservationId, $pfPaymentId, $amountGross);
        } elseif ($paymentStatus === 'FAILED') {
            // Update reservation status to 'failed' or 'cancelled' based on your logic
            $this->updateReservationToFailed($reservationId, $pfPaymentId);
        } elseif ($paymentStatus === 'PENDING') {
            // Optionally update status to 'pending' if not already set
            $this->updateReservationToPending($reservationId, $pfPaymentId);
        } else {
            // Handle other statuses if necessary, or log them
            $this->logger->info("IPN received with unhandled status: {$paymentStatus} for reservation {$reservationId}");
        }
    }

    private function updateReservationToPaid(string $reservationId, string $pfPaymentId, float $amount): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE reservations
            SET payment_status = 'paid',
                payfast_payment_id = ?,
                paid_at = NOW(),
                updated_at = NOW()
            WHERE id = ? AND payment_status IN ('pending', 'draft') -- Only update if still pending/draft
        ");
        $stmt->execute([$pfPaymentId, $reservationId]);

        if ($stmt->rowCount() > 0) {
            $this->logger->info("Reservation {$reservationId} marked as paid via PayFast (ID: {$pfPaymentId}).");
            // Potentially trigger other events like sending confirmation email via queue service
            // This could involve emitting an event like 'reservation.payment.completed'
            // or calling an email queue service directly here.
            // $this->emitEvent('reservation.payment.completed', ['reservation_id' => $reservationId]);
        } else {
            $this->logger->warning("Attempted to mark reservation {$reservationId} as paid, but it was not in 'pending' status or did not exist.");
        }
    }

    private function updateReservationToFailed(string $reservationId, string $pfPaymentId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE reservations
            SET payment_status = 'failed',
                payfast_payment_id = ?,
                updated_at = NOW()
            WHERE id = ? AND payment_status = 'pending' -- Only update if still pending
        ");
        $stmt->execute([$pfPaymentId, $reservationId]);

        if ($stmt->rowCount() > 0) {
            $this->logger->info("Reservation {$reservationId} marked as failed via PayFast (ID: {$pfPaymentId}).");
        } else {
            $this->logger->warning("Attempted to mark reservation {$reservationId} as failed, but it was not in 'pending' status or did not exist.");
        }
    }

    private function updateReservationToPending(string $reservationId, string $pfPaymentId): void
    {
        // Optional: Update status if it's PENDING. Usually, status is set to pending before redirect.
        // This might be useful if the status was incorrectly set or to confirm the state.
        $stmt = $this->pdo->prepare("
            UPDATE reservations
            SET payfast_payment_id = ?,
                updated_at = NOW()
            WHERE id = ? AND payment_status = 'pending'
        ");
        $stmt->execute([$pfPaymentId, $reservationId]);
        if ($stmt->rowCount() > 0) {
            $this->logger->debug("Reservation {$reservationId} status confirmed as pending via PayFast (ID: {$pfPaymentId}).");
        }
    }

    /**
     * Placeholder for decryption logic. This must be implemented based on your core's encryption method.
     */
    private function decryptMerchantKey(string $encryptedKey): string
    {
        // This is a critical part. You need to use the same encryption/decryption method
        // that your core system uses for storing secrets in extension_settings or organizations table.
        // Example: return App\Support\Encryptor::decrypt($encryptedKey);
        // Or use a service provided by the core system.
        // For now, we'll throw an exception to highlight that this needs to be implemented.
        throw new \Exception("Decryption method for PayFast keys not implemented in IPN Handler. Please integrate with your core's encryption service.");
    }

}