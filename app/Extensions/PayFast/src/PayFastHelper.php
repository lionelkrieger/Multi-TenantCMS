<?php
// src/PayFastHelper.php
declare(strict_types=1);

namespace App\Extensions\PayFast;

class PayFastHelper
{
    // Add helper functions here if needed, e.g., for formatting amounts, validating data structures, etc.
    public static function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', ''); // Ensures 2 decimal places, dot separator
    }

    public static function sanitizeInput(string $input): string
    {
        // Basic sanitization, might need to be more robust depending on use
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}