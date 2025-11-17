<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Extension;
use JsonException;

final class ExtensionRepository extends Repository
{
    /**
     * @return array<int, Extension>
     */
    public function listAll(): array
    {
        $records = $this->fetchAll('SELECT * FROM extensions ORDER BY display_name ASC');
        return array_map(static fn (array $record): Extension => Extension::fromArray($record), $records);
    }

    public function findById(string $extensionId): ?Extension
    {
        $record = $this->fetchOne('SELECT * FROM extensions WHERE id = :id LIMIT 1', ['id' => $extensionId]);
        return $record ? Extension::fromArray($record) : null;
    }

    public function findBySlug(string $slug): ?Extension
    {
        $record = $this->fetchOne('SELECT * FROM extensions WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
        return $record ? Extension::fromArray($record) : null;
    }

    public function updateAllowOrgToggle(string $extensionId, bool $allow): void
    {
        $this->update(
            'UPDATE extensions SET allow_org_toggle = :allow_org_toggle, updated_at = NOW() WHERE id = :id',
            [
                'allow_org_toggle' => $allow ? 1 : 0,
                'id' => $extensionId,
            ]
        );
    }

    /**
     * @return array<string, int>
     */
    public function enabledCounts(): array
    {
        $rows = $this->fetchAll('SELECT extension_id, SUM(enabled) AS total_enabled FROM extension_settings GROUP BY extension_id');
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['extension_id']] = (int) ($row['total_enabled'] ?? 0);
        }

        return $counts;
    }

    /**
     * @return array<int, array{extension: Extension, enabled: bool, settings: array<string, mixed>}> 
     */
    public function listWithOrganizationContext(string $organizationId): array
    {
        $records = $this->fetchAll(
            'SELECT e.*, es.enabled AS org_enabled, es.settings AS org_settings
             FROM extensions e
             LEFT JOIN extension_settings es ON es.extension_id = e.id AND es.organization_id = :organization_id
             ORDER BY e.display_name ASC',
            ['organization_id' => $organizationId]
        );

        $results = [];
        foreach ($records as $record) {
            $extension = Extension::fromArray($record);
            $settings = $this->decodeSettings($record['org_settings'] ?? null);
            $results[] = [
                'extension' => $extension,
                'enabled' => (bool) ($record['org_enabled'] ?? false),
                'settings' => $settings,
            ];
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSettings(?string $payload): array
    {
        if ($payload === null || $payload === '') {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (JsonException $exception) {
            app_logger()->warning('Failed to decode extension settings payload', [
                'error' => $exception->getMessage(),
            ]);
            return [];
        }
    }
}
