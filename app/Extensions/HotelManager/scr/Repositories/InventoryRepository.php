<?php
// src/Repositories/InventoryRepository.php
declare(strict_types=1);

namespace App\Extensions\HotelManager\Repositories;

use PDO;

class InventoryRepository
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
            INSERT INTO room_types (
                id, property_id, name, short_description, description, base_price,
                max_adults, max_children, total_units, status, sort_order,
                primary_image_path, gallery_images, amenity_ids
            ) VALUES (
                :id, :property_id, :name, :short_description, :description, :base_price,
                :max_adults, :max_children, :total_units, :status, :sort_order,
                :primary_image_path, :gallery_images, :amenity_ids
            )
        ");
        $stmt->execute([
            ':id' => $id,
            ':property_id' => $data['property_id'],
            ':name' => $data['name'],
            ':short_description' => $data['short_description'] ?? null,
            ':description' => $data['description'] ?? null,
            ':base_price' => $data['base_price'],
            ':max_adults' => $data['max_adults'] ?? 2,
            ':max_children' => $data['max_children'] ?? 1,
            ':total_units' => $data['total_units'] ?? 1,
            ':status' => $data['status'] ?? 'active',
            ':sort_order' => $data['sort_order'] ?? 0,
            ':primary_image_path' => $data['primary_image_path'] ?? null,
            ':gallery_images' => json_encode($data['gallery_images'] ?? []),
            ':amenity_ids' => json_encode($data['amenity_ids'] ?? [])
        ]);

        return $id;
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM room_types WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByProperty(string $propertyId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM room_types WHERE property_id = ? AND status = 'active' ORDER BY sort_order ASC");
        $stmt->execute([$propertyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(string $id, array $data): bool
    {
        $setParts = [];
        $params = [':id' => $id];
        foreach ($data as $key => $value) {
            if (in_array($key, ['name', 'short_description', 'description', 'base_price', 'max_adults', 'max_children', 'total_units', 'status', 'sort_order', 'primary_image_path'])) {
                 $setParts[] = "$key = :$key";
                 $params[":$key"] = $value;
            } elseif ($key === 'gallery_images' || $key === 'amenity_ids') {
                 $setParts[] = "$key = :$key";
                 $params[":$key"] = json_encode($value);
            }
        }

        if (empty($setParts)) {
            return false; // No valid fields to update
        }

        $sql = "UPDATE room_types SET " . implode(', ', $setParts) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM room_types WHERE id = ?");
        return $stmt->execute([$id]);
    }
}