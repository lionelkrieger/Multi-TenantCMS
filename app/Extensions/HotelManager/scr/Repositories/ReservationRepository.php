<?php
// src/Repositories/ReservationRepository.php
declare(strict_types=1);

namespace App\Extensions\HotelManager\Repositories;

use PDO;

class ReservationRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(array $data): string
    {
        $id = bin2hex(random_bytes(18)); // Generate ID
        $stmt = $this->pdo->prepare("
            INSERT INTO hotel_reservations (
                id, organization_id, property_id, guest_id, room_type_id,
                check_in_date, check_out_date, num_adults, num_children,
                base_amount, discount_amount, final_amount,
                payment_method, payment_status, status, special_requests
            ) VALUES (
                :id, :organization_id, :property_id, :guest_id, :room_type_id,
                :check_in_date, :check_out_date, :num_adults, :num_children,
                :base_amount, :discount_amount, :final_amount,
                :payment_method, :payment_status, :status, :special_requests
            )
        ");
        $stmt->execute([
            ':id' => $id,
            ':organization_id' => $data['organization_id'],
            ':property_id' => $data['property_id'],
            ':guest_id' => $data['guest_id'],
            ':room_type_id' => $data['room_type_id'],
            ':check_in_date' => $data['check_in_date'],
            ':check_out_date' => $data['check_out_date'],
            ':num_adults' => $data['num_adults'],
            ':num_children' => $data['num_children'],
            ':base_amount' => $data['base_amount'],
            ':discount_amount' => $data['discount_amount'],
            ':final_amount' => $data['final_amount'],
            ':payment_method' => $data['payment_method'],
            ':payment_status' => $data['payment_status'],
            ':status' => $data['status'],
            ':special_requests' => $data['special_requests'] ?? null
        ]);

        return $id;
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM hotel_reservations WHERE id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Counts reservations for a specific room type that overlap with a given date range.
     */
    public function getOverlappingReservationCount(string $roomTypeId, string $checkInDate, string $checkOutDate): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM hotel_reservations
            WHERE room_type_id = :room_type_id
              AND status IN ('confirmed', 'checked_in') -- Exclude cancelled/completed
              AND NOT (check_out_date <= :check_in_date OR check_in_date >= :check_out_date) -- Overlap condition
        ");
        $stmt->execute([
            ':room_type_id' => $roomTypeId,
            ':check_in_date' => $checkInDate,
            ':check_out_date' => $checkOutDate
        ]);
        return (int)$stmt->fetchColumn();
    }

    // Add other methods like update, findByGuest, findByProperty, etc.
}