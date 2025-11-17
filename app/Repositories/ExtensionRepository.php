<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Extension;
use App\Services\ExtensionSettingsService;

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
        $rows = $this->fetchAll(
            'SELECT extension_id, JSON_UNQUOTE(JSON_EXTRACT(`value`, "$.value")) AS raw_value
             FROM extension_settings
             WHERE `key` = :enabled_key',
            ['enabled_key' => ExtensionSettingsService::ENABLED_KEY]
        );

        $counts = [];
        foreach ($rows as $row) {
            $extensionId = (string) $row['extension_id'];
            $raw = $row['raw_value'];
            $isTrue = $raw === 'true' || $raw === '1' || $raw === '"1"';
            if ($isTrue) {
                $counts[$extensionId] = ($counts[$extensionId] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
