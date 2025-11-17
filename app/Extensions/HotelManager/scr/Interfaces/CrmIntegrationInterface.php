<?php
// src/Interfaces/CrmIntegrationInterface.php
declare(strict_types=1);

namespace App\Extensions\HotelManager\Interfaces;

/**
 * Interface for the CRM extension to implement, allowing the Hotel Manager to consume guest data.
 */
interface CrmIntegrationInterface
{
    public function getGuestProfile(string $guestId): array;
    public function getGuestLoyaltyTier(string $guestId): string;
    public function getGuestPreferences(string $guestId): array;
    public function updateGuestLtv(string $guestId, float $amount): void;
    public function addGuestTransaction(string $guestId, array $transaction): void;
}