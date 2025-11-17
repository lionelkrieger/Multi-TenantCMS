<?php
// src/PayFastGateway.php
declare(strict_types=1);

namespace App\Extensions\PayFast;

use PDO;
use App\Extensions\ExtensionContext;
use App\Extensions\PayFast\PayFastAPIClient;

class PayFastGateway
{
    private PDO $pdo;
    private ExtensionContext $context;
    private array $config; // Contains decrypted credentials and settings

    public function __construct(PDO $connection, array $config)
    {
        $this->pdo = $connection;
        $this->config = $config; // e.g., ['merchant_id' => '...', 'merchant_key' => '...', 'sandbox_mode' => true/false]
    }

    /**
     * Handles the event when a reservation payment is initiated (guest chooses "Pay Online").
     * Fetches org settings, prepares PayFast data, and redirects.
     */
    public function handleInitiatePayment(array $eventData): void
    {
        $reservationId = $eventData['reservation_id'];
        $organizationId = $eventData['organization_id'];

        // 1. Fetch organization settings (merchant ID, key, sandbox mode)
        $orgSettings = $this->getOrganizationPayFastSettings($organizationId);

        if (!$orgSettings || !$orgSettings['enabled']) {
             $this->context->logger->error("PayFast not enabled for organization {$organizationId}.");
             // Throw an exception or handle error appropriately
             // This might involve redirecting the user back with an error message.
             throw new \Exception("PayFast is not enabled for this property.");
        }

        // 2. Prepare data for PayFast API Client
        $paymentData = [
            'reservation_id' => $reservationId,
            'property_id' => $eventData['property_id'],
            'guest_id' => $eventData['guest_id'],
            'first_name' => $eventData['guest']['first_name'],
            'last_name' => $eventData['guest']['last_name'],
            'email' => $eventData['guest']['email'],
            'amount' => $eventData['reservation']['final_amount'],
            'item_name' => "Reservation {$reservationId}",
            'item_description' => "Payment for reservation at {$eventData['property']['name']}",
            'num_nights' => $eventData['reservation']['nights'],
            'num_guests' => $eventData['reservation']['num_adults'] + $eventData['reservation']['num_children'],
            'return_url' => $eventData['return_url'], // Passed from reservation flow
            'cancel_url' => $eventData['cancel_url'], // Passed from reservation flow
            'notify_url' => $eventData['notify_url'], // Passed from reservation flow
        ];

        // 3. Initialize API Client with org-specific credentials
        $apiClient = new PayFastAPIClient(
            $orgSettings['merchant_id'],
            $orgSettings['merchant_key'],
            $orgSettings['sandbox_mode'],
            $this->context // Assuming context is available or injected differently if needed
        );

        // 4. Prepare payment and get redirect URL
        $payfastResponse = $apiClient->preparePayment($paymentData);

        // 5. Update reservation status to 'pending_payment' or similar (before redirect)
        $this->updateReservationPaymentStatus($reservationId, 'pending', 'payfast_online');

        // 6. Redirect the user to PayFast
        // This is typically done by sending a redirect response from the controller handling the 'reservation.payment.initiate' event.
        // The gateway service prepares the data, but the actual redirect happens in the calling controller/route.
        // For now, we'll just return the URL.
        // In a real scenario, the event listener would handle the redirect response.
        header('Location: ' . $payfastResponse['redirect_url']);
        exit(); // Important to stop execution after redirect
    }


    /**
     * Fetches PayFast settings for a specific organization.
     * Assumes credentials are stored encrypted in the organizations table as per install.php.
     * This method needs access to a decryption service.
     */
    private function getOrganizationPayFastSettings(string $organizationId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT payfast_enabled, payfast_merchant_id, payfast_merchant_key, payfast_sandbox_mode,
                   payfast_test_merchant_id, payfast_test_merchant_key
            FROM organizations WHERE id = ?
        ");
        $stmt->execute([$organizationId]);
        $org = $stmt->fetch();

        if (!$org || !$org['payfast_enabled']) {
            return null;
        }

        // Determine which credentials to use based on sandbox mode
        $isSandbox = $org['payfast_sandbox_mode'];
        $merchantId = $isSandbox ? $org['payfast_test_merchant_id'] : $org['payfast_merchant_id'];
        $merchantKey = $isSandbox ? $org['payfast_test_merchant_key'] : $org['payfast_merchant_key'];

        // IMPORTANT: Decrypt the merchant key before returning
        // This requires a service to decrypt the stored value.
        // For now, assume a simple function exists or the core system provides decryption.
        // $decryptedKey = App\Support\Encryptor::decrypt($merchantKey);
        // For this example, we'll assume the decryption happens outside or is handled by the caller.
        // The core system should provide a way to decrypt values stored in extension_settings or organizations table.

        // This is a simplification. In reality, the decryption logic depends on how you implemented encryption in the core.
        // You might need to pass a decryption service into this class.
        // For now, let's assume the keys are stored decrypted in the $config array passed during construction,
        // which is populated by the core system after fetching and decrypting from the DB.
        // So, we might just need to select the correct pair from the config based on the org ID and sandbox mode.
        // This requires the core to pass the correct config slice for the org.

        // For this code, we'll assume the config contains the decrypted keys for the current org context.
        // $this->config = ['merchant_id' => 'decrypted_id', 'merchant_key' => 'decrypted_key', 'sandbox_mode' => true];
        // Therefore, the call to preparePayment already has the correct, decrypted credentials.

        // However, if $this->config is global or for the extension instance, we need to fetch the org-specific settings here.
        // This means the core system needs to fetch the org settings, decrypt them, and potentially call this method with them,
        // OR this method fetches them directly using the PDO and a decryption helper.

        // Let's assume a decryption helper is available or the core system has already decrypted the keys for this org.
        // We'll use a placeholder decryption call.
        try {
            $decryptedKey = $this->decryptMerchantKey($merchantKey); // This needs to be implemented based on your core's encryption
        } catch (\Exception $e) {
             $this->context->logger->error("Failed to decrypt PayFast merchant key for org {$organizationId}: " . $e->getMessage());
             return null;
        }


        return [
            'enabled' => $org['payfast_enabled'],
            'merchant_id' => $merchantId,
            'merchant_key' => $decryptedKey, // Use the decrypted key
            'sandbox_mode' => $isSandbox,
        ];
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
        throw new \Exception("Decryption method for PayFast keys not implemented. Please integrate with your core's encryption service.");
        // return $encryptedKey; // WRONG: Never do this in production.
    }


    /**
     * Updates the reservation's payment status in the database.
     * This is called before redirecting to PayFast.
     */
    private function updateReservationPaymentStatus(string $reservationId, string $paymentStatus, string $paymentMethod): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE reservations
            SET payment_status = ?, payment_method = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$paymentStatus, $paymentMethod, $reservationId]);
    }

    // Add methods for validating settings, testing connection, etc.
    public function testConnection(string $orgId): bool
    {
        // This would involve attempting to construct an API client with the org's credentials
        // and perhaps making a simple API call (if available) or just verifying the keys are set.
        $settings = $this->getOrganizationPayFastSettings($orgId);
        return $settings !== null && !empty($settings['merchant_id']) && !empty($settings['merchant_key']);
    }
}