<?php
// install.php
declare(strict_types=1);

use App\Extensions\ExtensionContext;

return static function (ExtensionContext $context): void {
    $pdo = $context->connection;

    // Add PayFast-specific columns to the organizations table
    // This aligns with the protocol spec and allows the core settings UI to manage them.
    try {
        $pdo->exec("
            ALTER TABLE organizations
            ADD COLUMN payfast_enabled BOOLEAN DEFAULT FALSE,
            ADD COLUMN payfast_merchant_id VARCHAR(50) NULL, -- Encrypted in application layer
            ADD COLUMN payfast_merchant_key TEXT NULL, -- Encrypted in application layer
            ADD COLUMN payfast_sandbox_mode BOOLEAN DEFAULT FALSE,
            ADD COLUMN payfast_test_merchant_id VARCHAR(50) NULL, -- Encrypted in application layer
            ADD COLUMN payfast_test_merchant_key TEXT NULL; -- Encrypted in application layer
        ");

        // Add PayFast-specific column to reservations table to store the PayFast transaction ID
        $pdo->exec("
            ALTER TABLE reservations
            ADD COLUMN payfast_payment_id VARCHAR(50) NULL;
        ");

        $context->logger->info('PayFast extension tables updated.');
    } catch (\PDOException $e) {
        // Handle the case where columns already exist
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            $context->logger->info('PayFast columns already exist, skipping.');
        } else {
            $context->logger->error('Error updating tables for PayFast extension: ' . $e->getMessage());
            throw $e; // Re-throw if it's a different error
        }
    }
};