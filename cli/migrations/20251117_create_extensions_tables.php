<?php

declare(strict_types=1);

require __DIR__ . '/../../app/includes/bootstrap.php';

$connection = Database::connection();

/**
 * @param PDO $connection
 * @param string $table
 */
function tableExists(PDO $connection, string $table): bool
{
    $statement = $connection->prepare('SHOW TABLES LIKE :table');
    $statement->execute(['table' => $table]);
    return (bool) $statement->fetchColumn();
}

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

function slugify(string $value): string
{
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: generate_uuid_v4();
}

function defaultExtensionPath(string $slug, string $file): string
{
    $segments = preg_split('/[\\\/]+/', $slug) ?: [];
    $segments = array_filter($segments, static fn ($segment) => $segment !== '');
    $segments = array_map(static fn ($segment) => str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $segment))), $segments);
    $prefix = $segments ? implode('/', $segments) : 'Extension';
    return sprintf('app/Extensions/%s/%s', $prefix, $file);
}

try {
    $connection->beginTransaction();

    $extensionsTableExists = tableExists($connection, 'extensions');
    if (!$extensionsTableExists) {
        $connection->exec(<<<SQL
            CREATE TABLE extensions (
                id VARCHAR(36) PRIMARY KEY NOT NULL,
                slug VARCHAR(150) NOT NULL UNIQUE,
                name VARCHAR(150) NOT NULL,
                display_name VARCHAR(150) NOT NULL,
                version VARCHAR(20) NOT NULL,
                author VARCHAR(150) NULL,
                description TEXT NULL,
                homepage_url VARCHAR(255) NULL,
                entry_point VARCHAR(255) NOT NULL,
                manifest_path VARCHAR(255) NOT NULL,
                status ENUM('installed','active','inactive','error') DEFAULT 'inactive',
                allow_org_toggle BOOLEAN DEFAULT FALSE,
                requires_core_version VARCHAR(20) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE INDEX idx_extensions_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    } else {
        $columns = tableColumns($connection, 'extensions');
        if (!in_array('slug', $columns, true)) {
            $connection->exec("ALTER TABLE extensions ADD COLUMN slug VARCHAR(150) NOT NULL AFTER id");
            $connection->exec("ALTER TABLE extensions ADD UNIQUE INDEX idx_extensions_slug (slug)");
        }
        if (!in_array('homepage_url', $columns, true)) {
            $connection->exec("ALTER TABLE extensions ADD COLUMN homepage_url VARCHAR(255) NULL AFTER description");
        }
        if (!in_array('entry_point', $columns, true)) {
            $connection->exec("ALTER TABLE extensions ADD COLUMN entry_point VARCHAR(255) NOT NULL DEFAULT '' AFTER homepage_url");
        }
        if (!in_array('manifest_path', $columns, true)) {
            $connection->exec("ALTER TABLE extensions ADD COLUMN manifest_path VARCHAR(255) NOT NULL DEFAULT '' AFTER entry_point");
        }
        if (!in_array('allow_org_toggle', $columns, true)) {
            $connection->exec("ALTER TABLE extensions ADD COLUMN allow_org_toggle BOOLEAN DEFAULT FALSE AFTER status");
        }
        $connection->exec("ALTER TABLE extensions MODIFY COLUMN status ENUM('installed','active','inactive','error') DEFAULT 'inactive'");
    }

    $settingsTableExists = tableExists($connection, 'extension_settings');
    if (!$settingsTableExists) {
        $connection->exec(<<<SQL
            CREATE TABLE extension_settings (
                id VARCHAR(36) PRIMARY KEY NOT NULL,
                extension_id VARCHAR(36) NOT NULL,
                organization_id VARCHAR(36) NOT NULL,
                settings JSON NOT NULL,
                enabled BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (extension_id) REFERENCES extensions(id) ON DELETE CASCADE,
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                UNIQUE KEY unique_org_extension (organization_id, extension_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    // Populate slug/paths for legacy records
    $existingExtensionsStatement = $connection->query('SELECT id, name, slug, entry_point, manifest_path FROM extensions');
    if ($existingExtensionsStatement !== false) {
        $existingExtensions = $existingExtensionsStatement->fetchAll(PDO::FETCH_ASSOC);
        $usedSlugs = [];
        foreach ($existingExtensions as $record) {
            $slug = $record['slug'] ?? '';
            if ($slug === '' || $slug === null) {
                $baseSlug = slugify((string) $record['name']);
                $slugCandidate = $baseSlug;
                $suffix = 1;
                while (in_array($slugCandidate, $usedSlugs, true)) {
                    $slugCandidate = $baseSlug . '-' . $suffix;
                    $suffix++;
                }
                $slug = $slugCandidate;
            }
            $usedSlugs[] = $slug;

            $entryPoint = $record['entry_point'] ?? '';
            if ($entryPoint === '') {
                $entryPoint = defaultExtensionPath($slug, 'bootstrap.php');
            }

            $manifestPath = $record['manifest_path'] ?? '';
            if ($manifestPath === '') {
                $manifestPath = defaultExtensionPath($slug, 'extension.json');
            }

            $update = $connection->prepare('UPDATE extensions SET slug = :slug, entry_point = :entry_point, manifest_path = :manifest_path WHERE id = :id');
            $update->execute([
                'slug' => $slug,
                'entry_point' => $entryPoint,
                'manifest_path' => $manifestPath,
                'id' => $record['id'],
            ]);
        }
    }

    $seedExtensions = [
        [
            'slug' => 'platform/payfast',
            'name' => 'payfast',
            'display_name' => 'PayFast Payment Gateway',
            'version' => '0.1.0',
            'author' => 'Platform Core Team',
            'description' => 'Native PayFast integration for online and offline reservations.',
            'homepage_url' => 'https://developers.payfast.io/',
            'entry_point' => 'app/Extensions/PayFast/bootstrap.php',
            'manifest_path' => 'app/Extensions/PayFast/extension.json',
        ],
        [
            'slug' => 'platform/gtm',
            'name' => 'gtm',
            'display_name' => 'Google Tag Manager Integration',
            'version' => '0.1.0',
            'author' => 'Platform Core Team',
            'description' => 'GTM + enhanced conversions data layer publisher.',
            'homepage_url' => 'https://marketingplatform.google.com/about/tag-manager/',
            'entry_point' => 'app/Extensions/GTM/bootstrap.php',
            'manifest_path' => 'app/Extensions/GTM/extension.json',
        ],
    ];

    $insertExtension = $connection->prepare('INSERT INTO extensions (id, slug, name, display_name, version, author, description, homepage_url, entry_point, manifest_path, status, allow_org_toggle, requires_core_version, created_at, updated_at) VALUES (:id, :slug, :name, :display_name, :version, :author, :description, :homepage_url, :entry_point, :manifest_path, :status, :allow_org_toggle, :requires_core_version, NOW(), NOW())');

    foreach ($seedExtensions as $extension) {
        $lookup = $connection->prepare('SELECT id FROM extensions WHERE slug = :slug');
        $lookup->execute(['slug' => $extension['slug']]);
        $exists = $lookup->fetch(PDO::FETCH_ASSOC);
        if ($exists) {
            continue;
        }

        $insertExtension->execute([
            'id' => generate_uuid_v4(),
            'slug' => $extension['slug'],
            'name' => $extension['name'],
            'display_name' => $extension['display_name'],
            'version' => $extension['version'],
            'author' => $extension['author'],
            'description' => $extension['description'],
            'homepage_url' => $extension['homepage_url'],
            'entry_point' => $extension['entry_point'],
            'manifest_path' => $extension['manifest_path'],
            'status' => 'inactive',
            'allow_org_toggle' => 1,
            'requires_core_version' => null,
        ]);
    }

    $connection->exec("UPDATE extensions SET allow_org_toggle = 1 WHERE slug IN ('platform/payfast','platform/gtm')");

    $connection->commit();
    echo "Migration complete: extensions + extension_settings ensured." . PHP_EOL;
} catch (Throwable $throwable) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    fwrite(STDERR, 'Migration failed: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
