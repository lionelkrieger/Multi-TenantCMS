<?php
// bootstrap.php
declare(strict_types=1);

use App\Extensions\ExtensionContext;
use App\Extensions\ExtensionRegistry;

return static function (ExtensionContext $context, ExtensionRegistry $registry): void {
    // Register the main PayFast Gateway service
    $registry->registerService('payfast.gateway', function () use ($context) {
        return new App\Extensions\PayFast\PayFastGateway($context->connection, $context->config, $context->logger);
    });

    // Register the IPN handler
    $registry->registerService('payfast.ipn_handler', function () use ($context) {
        return new App\Extensions\PayFast\PayFastIPNHandler($context->connection, $context->logger);
    });

    // Listen for events from the reservation system
    $registry->addEventListener('reservation.payment.initiate', function ($eventData) use ($registry) {
        $gateway = $registry->getService('payfast.gateway');
        $gateway->handleInitiatePayment($eventData);
    });

    // Register the IPN route (public endpoint)
    $registry->registerRoute('public', 'POST', '/api/payfast-ipn', function () use ($registry) {
        $handler = $registry->getService('payfast.ipn_handler');
        $handler->handleIPN();
    });

    // Register an admin route if the core system needs to call specific PayFast methods for validation/testing
    // The main configuration UI is likely handled by the core system reading/writing to the 'organizations' table fields.
    $registry->registerRoute('admin', 'POST', '/admin/payfast/settings', function () use ($registry, $context) {
        // Example: Handle a test connection request from the admin settings page
        if ($_POST['action'] === 'test_connection') {
             $gateway = $registry->getService('payfast.gateway');
             $orgId = $_POST['organization_id']; // Assume passed securely
             $result = $gateway->testConnection($orgId);
             // Return JSON response for AJAX call
             header('Content-Type: application/json');
             echo json_encode(['success' => $result]);
             exit;
        }
        // Handle other potential admin actions if needed
    });

    $context->logger->info('PayFast extension loaded with services and listeners.');
};