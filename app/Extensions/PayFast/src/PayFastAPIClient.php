<?php
// src/PayFastAPIClient.php
declare(strict_types=1);

namespace App\Extensions\PayFast;

use App\Extensions\ExtensionContext;

class PayFastAPIClient
{
    private string $merchantId;
    private string $merchantKey;
    private bool $isSandbox;
    private ExtensionContext $context;

    public function __construct(string $merchantId, string $merchantKey, bool $isSandbox, ExtensionContext $context)
    {
        $this->merchantId = $merchantId;
        $this->merchantKey = $merchantKey;
        $this->isSandbox = $isSandbox;
        $this->context = $context;
    }

    /**
     * Generate the payment request payload and redirect URL.
     * This doesn't make an HTTP call itself, it just prepares the data for the redirect.
     */
    public function preparePayment(array $paymentData): array
    {
        $timestamp = date('Y-m-d H:i:s');
        $returnUrl = $paymentData['return_url'] ?? 'https://yourdomain.com/reservation/confirmed';
        $cancelUrl = $paymentData['cancel_url'] ?? 'https://yourdomain.com/reservation/cancelled';
        $notifyUrl = $paymentData['notify_url'] ?? 'https://yourdomain.com/api/payfast-ipn'; // This should be the registered route

        $postData = [
            'merchant_id' => $this->merchantId,
            'merchant_key' => $this->merchantKey,
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'notify_url' => $notifyUrl,
            'name_first' => $paymentData['first_name'] ?? '',
            'name_last' => $paymentData['last_name'] ?? '',
            'email_address' => $paymentData['email'] ?? '',
            'm_payment_id' => $paymentData['reservation_id'], // Merchant's unique ID for the transaction
            'amount' => number_format($paymentData['amount'], 2, '.', ''),
            'item_name' => $paymentData['item_name'] ?? 'Reservation Payment',
            'item_description' => $paymentData['item_description'] ?? 'Payment for reservation',
            'custom_str1' => $paymentData['reservation_id'], // Store reservation ID for easy lookup later
            'custom_str2' => $paymentData['property_id'] ?? '', // Store property ID if needed
            'custom_str3' => $paymentData['guest_id'] ?? '', // Store guest ID if needed
            'custom_int1' => (int)($paymentData['num_nights'] ?? 1), // Example custom int
            'custom_int2' => (int)($paymentData['num_guests'] ?? 1), // Example custom int
            'source' => 'skylight_hospitality_v1', // Identifies the source of the transaction
            'version' => '2', // PayFast API version
            'timestamp' => $timestamp, // For potential future use or validation
        ];

        // Calculate the signature (MD5 hash of sorted parameters)
        $signatureString = $this->buildSignatureString($postData);
        $signature = md5($signatureString);

        $postData['signature'] = $signature;

        // Determine the PayFast URL based on sandbox mode
        $payfastUrl = $this->isSandbox ? 'https://sandbox.payfast.co.za/eng/process' : 'https://www.payfast.co.za/eng/process';

        return [
            'redirect_url' => $payfastUrl,
            'post_data' => $postData,
        ];
    }

    /**
     * Builds the string used for signature calculation.
     * Parameters must be in alphabetical order, excluding the signature field itself.
     */
    private function buildSignatureString(array $postData): string
    {
        $sortedData = $postData;
        ksort($sortedData); // Sort alphabetically by key
        unset($sortedData['signature']); // Exclude the signature field from the string

        $pairs = [];
        foreach ($sortedData as $key => $value) {
            // PayFast expects the string in the format: key1=value1&key2=value2
            $pairs[] = $key . '=' . $value;
        }

        return implode('&', $pairs);
    }

    /**
     * Verifies the IPN signature received from PayFast.
     * $postData is the $_POST received from PayFast.
     */
    public function verifyIPNSignature(array $postData): bool
    {
        if (!isset($postData['signature'])) {
            $this->context->logger->warning('IPN received without signature.');
            return false;
        }

        $receivedSignature = $postData['signature'];
        $signatureString = $this->buildSignatureString($postData); // Uses the same logic as preparePayment
        $expectedSignature = md5($signatureString);

        $isValid = hash_equals($expectedSignature, $receivedSignature); // Use hash_equals for timing attack prevention

        if (!$isValid) {
            $this->context->logger->warning('IPN signature verification failed.', [
                'received' => $receivedSignature,
                'expected' => $expectedSignature,
                'calculated_string' => $signatureString,
            ]);
        } else {
            $this->context->logger->debug('IPN signature verification passed.');
        }

        return $isValid;
    }

    /**
     * Validates the merchant ID in the IPN against the configured one.
     */
    public function validateIPNMerchantId(string $receivedMerchantId): bool
    {
        $isValid = hash_equals($this->merchantId, $receivedMerchantId);
        if (!$isValid) {
            $this->context->logger->warning('IPN merchant ID mismatch.', [
                'received' => $receivedMerchantId,
                'configured' => $this->merchantId,
            ]);
        }
        return $isValid;
    }

    // Add methods for API calls if needed in the future (e.g., query transaction status)
    // public function queryTransactionStatus(string $pfPaymentId): array { ... }
}