<?php

declare(strict_types=1);

require __DIR__ . '/../../app/includes/bootstrap.php';

$connection = Database::connection();

/**
 * @param PDO $connection
 * @param string $table
 * @return string[]
 */
function tableColumns(PDO $connection, string $table): array
{
    $statement = $connection->query(sprintf('SHOW COLUMNS FROM %s', $table));
    return $statement !== false ? $statement->fetchAll(PDO::FETCH_COLUMN) : [];
}

try {
    $connection->beginTransaction();

    $columns = tableColumns($connection, 'extension_settings');
    $alreadyMigrated = in_array('key', $columns, true) && in_array('value', $columns, true) && !in_array('settings', $columns, true);
    if ($alreadyMigrated) {
        $connection->rollBack();
        echo "extension_settings already matches latest structure." . PHP_EOL;
        return;
    }

    if ($columns === []) {
        throw new RuntimeException('extension_settings table does not exist. Run the base migration first.');
    }

    $connection->exec(<<<SQL
        CREATE TABLE extension_settings_new (
            id VARCHAR(36) PRIMARY KEY NOT NULL,
            extension_id VARCHAR(36) NOT NULL,
            organization_id VARCHAR(36) NOT NULL,
            `key` VARCHAR(150) NOT NULL,
            `value` JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (extension_id) REFERENCES extensions(id) ON DELETE CASCADE,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            UNIQUE KEY unique_org_extension_key (extension_id, organization_id, `key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    $statement = $connection->query('SELECT * FROM extension_settings');
    if ($statement !== false) {
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $insert = $connection->prepare('INSERT INTO extension_settings_new (id, extension_id, organization_id, `key`, `value`, created_at, updated_at) VALUES (:id, :extension_id, :organization_id, :key, :value, :created_at, :updated_at)');

        foreach ($rows as $row) {
            if (isset($row['settings'])) {
                $decoded = json_decode((string) $row['settings'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $settingKey => $payload) {
                        if ($settingKey === '' || !is_string($settingKey)) {
                            continue;
                        }

                        if (!is_array($payload)) {
                            $payload = [
                                'value' => $payload,
                                'encrypted' => false,
                                'serialized' => !is_scalar($payload) && $payload !== null,
                            ];
                        }

                        $insert->execute([
                            'id' => generate_uuid_v4(),
                            'extension_id' => (string) $row['extension_id'],
                            'organization_id' => (string) $row['organization_id'],
                            'key' => (string) $settingKey,
                            'value' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                            'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                            'updated_at' => $row['updated_at'] ?? date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }

            if (array_key_exists('enabled', $row)) {
                $enabledPayload = [
                    'value' => (bool) $row['enabled'],
                    'encrypted' => false,
                    'serialized' => false,
                ];

                $insert->execute([
                    'id' => generate_uuid_v4(),
                    'extension_id' => (string) $row['extension_id'],
                    'organization_id' => (string) $row['organization_id'],
                    'key' => 'core.enabled',
                    'value' => json_encode($enabledPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => $row['updated_at'] ?? date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    $connection->exec('DROP TABLE extension_settings');
    $connection->exec('RENAME TABLE extension_settings_new TO extension_settings');

    $connection->commit();
    echo "extension_settings migrated to key/value structure." . PHP_EOL;
} catch (Throwable $throwable) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    fwrite(STDERR, 'Migration failed: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
