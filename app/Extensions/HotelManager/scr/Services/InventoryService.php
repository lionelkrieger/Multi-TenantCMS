<?php
// src/Services/InventoryService.php
declare(strict_types=1);

namespace App\Extensions\HotelManager\Services;

use App\Extensions\HotelManager\Repositories\InventoryRepository;
use App\Extensions\HotelManager\Repositories\HotelPropertyRepository;

class InventoryService
{
    private InventoryRepository $inventoryRepo;
    private HotelPropertyRepository $propertyRepo;

    public function __construct(InventoryRepository $inventoryRepo, HotelPropertyRepository $propertyRepo)
    {
        $this->inventoryRepo = $inventoryRepo;
        $this->propertyRepo = $propertyRepo;
    }

    public function createRoomType(array $data): string
    {
        return $this->inventoryRepo->create($data);
    }

    public function getRoomTypesForProperty(string $propertyId): array
    {
        return $this->inventoryRepo->findByProperty($propertyId);
    }

    public function updateRoomType(string $id, array $data): bool
    {
        return $this->inventoryRepo->update($id, $data);
    }

    public function deleteRoomType(string $id): bool
    {
        return $this->inventoryRepo->delete($id);
    }

    public function createProperty(array $data): string
    {
        return $this->propertyRepo->create($data);
    }

    public function getPropertyById(string $id): ?array
    {
        return $this->propertyRepo->findById($id);
    }
}