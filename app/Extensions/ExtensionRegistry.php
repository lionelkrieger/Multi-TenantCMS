<?php

declare(strict_types=1);

namespace App\Extensions;

use App\Extensions\Contracts\ExtensionRegistryInterface;
use App\Extensions\Contracts\ExtensionSettingsStoreInterface;
use App\Extensions\Exceptions\ExtensionException;
use App\Extensions\Exceptions\ManifestValidationException;
use App\Models\Extension;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class ExtensionRegistry implements ExtensionRegistryInterface
{
    private PDO $connection;

    public function __construct(
        private readonly ManifestValidator $validator = new ManifestValidator(),
        private readonly ?ExtensionSettingsStoreInterface $settingsStore = null,
        ?PDO $connection = null
    ) {
        $this->connection = $connection ?? \Database::connection();
    }

    /** @return Extension[] */
    public function all(): array
    {
        $statement = $this->connection->query('SELECT * FROM extensions ORDER BY display_name ASC');
        $records = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
        return array_map(static fn (array $record): Extension => Extension::fromArray($record), $records);
    }

    public function discover(): void
    {
        $manifestPaths = $this->findManifestFiles();
        foreach ($manifestPaths as $manifestPath) {
            $this->registerManifest($manifestPath);
        }
    }

    public function findBySlug(string $slug): ?Extension
    {
        $statement = $this->connection->prepare('SELECT * FROM extensions WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);
        return $record !== false ? Extension::fromArray($record) : null;
    }

    public function activate(string $slug, string $organizationId): void
    {
        $this->ensureSettingsStore()->setEnabled($slug, $organizationId, true);
    }

    public function deactivate(string $slug, string $organizationId): void
    {
        $this->ensureSettingsStore()->setEnabled($slug, $organizationId, false);
    }

    private function registerManifest(string $manifestPath): void
    {
        $contents = file_get_contents($manifestPath);
        if ($contents === false) {
            throw new ExtensionException(sprintf('Unable to read manifest at %s', $manifestPath));
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            throw new ManifestValidationException(sprintf('Manifest %s is not valid JSON.', $manifestPath));
        }

        $normalized = $this->validator->validate($decoded, $manifestPath);
        $extensionDir = dirname($manifestPath);
        $entryPoint = $this->normalizePath($extensionDir, $normalized['entry_point']);
        if (!file_exists($entryPoint)) {
            throw new ManifestValidationException(sprintf('Entry point %s referenced by manifest %s does not exist.', $entryPoint, $manifestPath));
        }

        $relativeManifest = $this->relativePath($manifestPath);
        $relativeEntry = $this->relativePath($entryPoint);

        $existing = $this->findBySlug($normalized['slug']);
        if ($existing === null) {
            $this->insertExtension($normalized, $relativeManifest, $relativeEntry);
            return;
        }

        $this->updateExtension($existing->id, $normalized, $relativeManifest, $relativeEntry);
    }

    /**
     * @return string[]
     */
    private function findManifestFiles(): array
    {
        $base = app_path('Extensions');
        if (!is_dir($base)) {
            return [];
        }

        $directoryIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
        $manifests = [];
        /** @var SplFileInfo $file */
        foreach ($directoryIterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if (strtolower($file->getFilename()) !== 'extension.json') {
                continue;
            }

            $manifests[] = $file->getPathname();
        }

        sort($manifests);
        return $manifests;
    }

    private function insertExtension(array $data, string $manifestPath, string $entryPoint): void
    {
        $statement = $this->connection->prepare('INSERT INTO extensions (id, slug, name, display_name, version, author, description, homepage_url, entry_point, manifest_path, status, allow_org_toggle, requires_core_version, created_at, updated_at) VALUES (:id, :slug, :name, :display_name, :version, :author, :description, :homepage_url, :entry_point, :manifest_path, :status, :allow_org_toggle, :requires_core_version, NOW(), NOW())');
        $statement->execute([
            'id' => generate_uuid_v4(),
            'slug' => $data['slug'],
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'version' => $data['version'],
            'author' => $data['author'],
            'description' => $data['description'],
            'homepage_url' => $data['homepage_url'],
            'entry_point' => $entryPoint,
            'manifest_path' => $manifestPath,
            'status' => 'inactive',
            'allow_org_toggle' => 0,
            'requires_core_version' => $data['requires_core_version'],
        ]);
    }

    private function updateExtension(string $id, array $data, string $manifestPath, string $entryPoint): void
    {
        $statement = $this->connection->prepare('UPDATE extensions SET name = :name, display_name = :display_name, version = :version, author = :author, description = :description, homepage_url = :homepage_url, entry_point = :entry_point, manifest_path = :manifest_path, requires_core_version = :requires_core_version, updated_at = NOW() WHERE id = :id');
        $statement->execute([
            'id' => $id,
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'version' => $data['version'],
            'author' => $data['author'],
            'description' => $data['description'],
            'homepage_url' => $data['homepage_url'],
            'entry_point' => $entryPoint,
            'manifest_path' => $manifestPath,
            'requires_core_version' => $data['requires_core_version'],
        ]);
    }

    private function relativePath(string $absolute): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return ltrim(str_replace($base, '', $absolute), DIRECTORY_SEPARATOR);
    }

    private function normalizePath(string $baseDir, string $entryPoint): string
    {
        if (str_starts_with($entryPoint, DIRECTORY_SEPARATOR) || preg_match('#^[A-Za-z]:\\#', $entryPoint) === 1) {
            return $entryPoint;
        }

        return rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($entryPoint, DIRECTORY_SEPARATOR);
    }

    private function ensureSettingsStore(): ExtensionSettingsStoreInterface
    {
        if ($this->settingsStore !== null) {
            return $this->settingsStore;
        }

        return new \App\Services\ExtensionSettingsService($this->connection);
    }
}
