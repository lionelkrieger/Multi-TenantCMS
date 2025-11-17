<?php

declare(strict_types=1);

namespace App\Extensions;

use App\Extensions\Commands\CommandRegistry;
use App\Extensions\Contracts\ExtensionLifecycleInterface;
use App\Extensions\Contracts\ExtensionRegistryInterface;
use App\Extensions\Contracts\ExtensionSettingsStoreInterface;
use App\Extensions\Events\EventDispatcher;
use App\Extensions\Events\EventDispatcherInterface;
use App\Extensions\HookRegistry;
use App\Extensions\Exceptions\ExtensionException;
use App\Extensions\Exceptions\ManifestValidationException;
use App\Models\Extension;
use JsonException;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

final class ExtensionRegistry implements ExtensionRegistryInterface
{
    private PDO $connection;
    private EventDispatcherInterface $eventDispatcher;
    private CommandRegistry $commandRegistry;

    /**
     * @var array<string, array<int, array{surface: string, method: string, path: string, handler: callable, capability: ?string, metadata: array}>>
     */
    private array $routeIntents = [];

    public function __construct(
        private readonly ManifestValidator $validator = new ManifestValidator(),
        private readonly ?ExtensionSettingsStoreInterface $settingsStore = null,
        ?PDO $connection = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?CommandRegistry $commandRegistry = null
    ) {
        $this->connection = $connection ?? \Database::connection();
        $this->eventDispatcher = $eventDispatcher ?? new EventDispatcher();
        $this->commandRegistry = $commandRegistry ?? new CommandRegistry();
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

    public function install(string $slug): void
    {
        $extension = $this->requireExtension($slug);
        $lifecycle = $this->loadLifecycle($extension);
        $context = $this->buildContext($extension, null);
        $lifecycle->install($context);
        $this->markInstalled($extension, $extension->version);
    }

    public function upgrade(string $slug): void
    {
        $extension = $this->requireExtension($slug);
        $lifecycle = $this->loadLifecycle($extension);
        $this->ensureInstalled($extension, $lifecycle);
    }

    public function uninstall(string $slug): void
    {
        $extension = $this->requireExtension($slug);
        $lifecycle = $this->loadLifecycle($extension);
        $context = $this->buildContext($extension, null);
        $lifecycle->uninstall($context);

        $this->eventDispatcher->removeListeners($extension->slug, null);
        $this->commandRegistry->unregisterByExtension($extension->slug);
        $this->clearRouteIntents($extension->slug, null);

        $statement = $this->connection->prepare('UPDATE extensions SET installed_version = NULL, status = :status, updated_at = NOW() WHERE id = :id');
        $statement->execute([
            'status' => 'inactive',
            'id' => $extension->id,
        ]);

        $deleteSettings = $this->connection->prepare('DELETE FROM extension_settings WHERE extension_id = :id');
        $deleteSettings->execute(['id' => $extension->id]);
    }

    public function activate(string $slug, string $organizationId): void
    {
        $extension = $this->requireExtension($slug);
        $lifecycle = $this->loadLifecycle($extension);
        $extension = $this->ensureInstalled($extension, $lifecycle);

        $context = $this->buildContext($extension, $organizationId);
        $lifecycle->activate($context);
        $routes = $this->collectRoutes($lifecycle, $context);
        $this->registerRouteIntents($extension->slug, $organizationId, $routes);

        $this->ensureSettingsStore()->setEnabled($slug, $organizationId, true);
        $this->updateStatus($extension->id, 'active');
    }

    public function deactivate(string $slug, string $organizationId): void
    {
        $extension = $this->requireExtension($slug);
        $lifecycle = $this->loadLifecycle($extension);
        $context = $this->buildContext($extension, $organizationId);
        $lifecycle->deactivate($context);

        $this->eventDispatcher->removeListeners($extension->slug, $organizationId);
        $this->commandRegistry->unregisterByExtension($extension->slug, $organizationId);
        $this->clearRouteIntents($extension->slug, $organizationId);

        $this->ensureSettingsStore()->setEnabled($slug, $organizationId, false);
    }

    /**
     * @return array<int, array{surface: string, method: string, path: string, handler: callable, capability: ?string, metadata: array}>
     */
    public function runtimeRoutes(string $slug, ?string $organizationId): array
    {
        $key = $this->routeKey($slug, $organizationId);
        if (isset($this->routeIntents[$key])) {
            return $this->routeIntents[$key];
        }

        $extension = $this->requireExtension($slug);
        $lifecycle = $this->loadLifecycle($extension);
        $context = $this->buildContext($extension, $organizationId);
        $routes = $this->collectRoutes($lifecycle, $context);
        if ($routes !== []) {
            $this->registerRouteIntents($slug, $organizationId, $routes);
        }

        return $routes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function doctor(?string $slug = null): array
    {
        $extensions = $slug !== null ? [$this->requireExtension($slug)] : $this->all();
        $reports = [];

        foreach ($extensions as $extension) {
            $report = [
                'slug' => $extension->slug,
                'manifest_path' => $extension->manifestPath,
                'status' => 'ok',
                'issues' => [],
                'db_counts' => $this->metadataCounts($extension->id),
                'manifest_counts' => null,
            ];

            $manifestFile = $this->absolutePath($extension->manifestPath);
            if (!file_exists($manifestFile)) {
                $report['status'] = 'error';
                $report['issues'][] = 'Manifest file missing.';
                $reports[] = $report;
                continue;
            }

            $contents = file_get_contents($manifestFile);
            if ($contents === false) {
                $report['status'] = 'error';
                $report['issues'][] = 'Unable to read manifest file.';
                $reports[] = $report;
                continue;
            }

            try {
                $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    throw new ManifestValidationException('Manifest JSON does not decode to an object.');
                }

                $normalized = $this->validator->validate($decoded, $manifestFile);
                $checksum = hash('sha256', $contents);
                $report['manifest_counts'] = [
                    'permissions' => count($normalized['permissions']),
                    'events' => count($normalized['hooks']['events']),
                    'commands' => count($normalized['hooks']['commands']),
                    'routes' => count($normalized['hooks']['routes']),
                    'panels' => count($normalized['hooks']['ui_panels']),
                ];

                if ($extension->manifestChecksum !== null && $extension->manifestChecksum !== $checksum) {
                    $report['status'] = 'warning';
                    $report['issues'][] = 'Manifest checksum differs from stored value. Run sync.';
                }

                $diffs = $this->compareMetadataCounts($report['db_counts'], $report['manifest_counts']);
                if ($diffs !== []) {
                    $report['status'] = $report['status'] === 'error' ? 'error' : 'warning';
                    $report['issues'][] = 'Metadata counts differ: ' . implode(', ', $diffs);
                }

                if ($report['issues'] === []) {
                    $report['issues'][] = 'No issues detected.';
                }
            } catch (Throwable $exception) {
                $report['status'] = 'error';
                $report['issues'][] = 'Validation failed: ' . $exception->getMessage();
            }

            $reports[] = $report;
        }

        return $reports;
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
        $checksum = hash('sha256', $contents);
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
        } else {
            $this->updateExtension($existing->id, $normalized, $relativeManifest, $relativeEntry);
        }

        $extension = $this->requireExtension($normalized['slug']);
        $this->persistManifestMetadata($extension, $normalized, $checksum);
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
        $statement = $this->connection->prepare('INSERT INTO extensions (id, slug, name, display_name, version, installed_version, author, description, homepage_url, entry_point, manifest_path, status, allow_org_toggle, requires_core_version, created_at, updated_at) VALUES (:id, :slug, :name, :display_name, :version, NULL, :author, :description, :homepage_url, :entry_point, :manifest_path, :status, :allow_org_toggle, :requires_core_version, NOW(), NOW())');
        $statement->execute([
            'id' => generate_uuid_v4(),
            'slug' => $data['slug'],
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'version' => $data['version'],
            'installed_version' => null,
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

    private function persistManifestMetadata(Extension $extension, array $manifest, string $checksum): void
    {
        try {
            $this->connection->beginTransaction();

            $this->deleteManifestMetadata($extension->id);
            $this->storePermissions($extension->id, $manifest['permissions']);
            $hooks = $manifest['hooks'];
            $this->storeHooks($extension->id, 'event', $hooks['events']);
            $this->storeHooks($extension->id, 'command', $hooks['commands']);
            $this->storeRoutes($extension->id, $hooks['routes']);
            $this->storePanels($extension->id, $hooks['ui_panels']);
            $this->updateManifestRecord($extension->id, $checksum, $manifest['signature']);

            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw new ExtensionException('Failed to persist manifest metadata: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function deleteManifestMetadata(string $extensionId): void
    {
        $tables = ['extension_permissions', 'extension_hooks', 'extension_routes', 'extension_panels'];
        foreach ($tables as $table) {
            $statement = $this->connection->prepare(sprintf('DELETE FROM %s WHERE extension_id = :extension_id', $table));
            $statement->execute(['extension_id' => $extensionId]);
        }
    }

    /**
     * @param string[] $permissions
     */
    private function storePermissions(string $extensionId, array $permissions): void
    {
        if ($permissions === []) {
            return;
        }

        $statement = $this->connection->prepare('INSERT INTO extension_permissions (id, extension_id, permission, created_at) VALUES (:id, :extension_id, :permission, NOW())');
        foreach ($permissions as $permission) {
            $statement->execute([
                'id' => generate_uuid_v4(),
                'extension_id' => $extensionId,
                'permission' => $permission,
            ]);
        }
    }

    /**
     * @param string[] $hooks
     */
    private function storeHooks(string $extensionId, string $type, array $hooks): void
    {
        if ($hooks === []) {
            return;
        }

        $statement = $this->connection->prepare('INSERT INTO extension_hooks (id, extension_id, hook_type, hook_name, metadata, created_at) VALUES (:id, :extension_id, :hook_type, :hook_name, :metadata, NOW())');
        $metadata = $this->encodeJson([]);
        foreach ($hooks as $hook) {
            $statement->execute([
                'id' => generate_uuid_v4(),
                'extension_id' => $extensionId,
                'hook_type' => $type,
                'hook_name' => $hook,
                'metadata' => $metadata,
            ]);
        }
    }

    /**
     * @param array<int, array{surface: string, method: string, path: string, capability: ?string, metadata: array}> $routes
     */
    private function storeRoutes(string $extensionId, array $routes): void
    {
        if ($routes === []) {
            return;
        }

        $statement = $this->connection->prepare('INSERT INTO extension_routes (id, extension_id, surface, method, path, capability, metadata, created_at) VALUES (:id, :extension_id, :surface, :method, :path, :capability, :metadata, NOW())');
        foreach ($routes as $route) {
            $statement->execute([
                'id' => generate_uuid_v4(),
                'extension_id' => $extensionId,
                'surface' => $route['surface'],
                'method' => $route['method'],
                'path' => $route['path'],
                'capability' => $route['capability'],
                'metadata' => $this->encodeJson($route['metadata'] ?? []),
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $panels
     */
    private function storePanels(string $extensionId, array $panels): void
    {
        if ($panels === []) {
            return;
        }

        $statement = $this->connection->prepare(
            'INSERT INTO extension_panels (id, extension_id, panel_key, title, component, schema_path, permissions, visible_to, org_toggle, sort_order, metadata, created_at)
             VALUES (:id, :extension_id, :panel_key, :title, :component, :schema_path, :permissions, :visible_to, :org_toggle, :sort_order, :metadata, NOW())'
        );

        foreach ($panels as $panel) {
            $statement->execute([
                'id' => generate_uuid_v4(),
                'extension_id' => $extensionId,
                'panel_key' => $panel['panel_key'],
                'title' => $panel['title'],
                'component' => $panel['component'],
                'schema_path' => $panel['schema_path'],
                'permissions' => $this->encodeJson($panel['permissions']),
                'visible_to' => $this->encodeJson($panel['visible_to']),
                'org_toggle' => $panel['org_toggle'] ? 1 : 0,
                'sort_order' => $panel['sort_order'] ?? 0,
                'metadata' => $this->encodeJson($panel['metadata']),
            ]);
        }
    }

    private function updateManifestRecord(string $extensionId, string $checksum, array $signature): void
    {
        $status = $this->normalizeSignatureStatus($signature['status'] ?? 'unknown');
        $vendor = $signature['vendor'] ?? null;

        $statement = $this->connection->prepare('UPDATE extensions SET manifest_checksum = :checksum, signature_status = :status, signature_vendor = :vendor, updated_at = NOW() WHERE id = :id');
        $statement->execute([
            'checksum' => $checksum,
            'status' => $status,
            'vendor' => $vendor,
            'id' => $extensionId,
        ]);
    }

    /**
     * @return array{permissions: int, events: int, commands: int, routes: int, panels: int}
     */
    private function metadataCounts(string $extensionId): array
    {
        return [
            'permissions' => $this->countMetadataRows('extension_permissions', $extensionId),
            'events' => $this->countMetadataRows('extension_hooks', $extensionId, "hook_type = 'event'"),
            'commands' => $this->countMetadataRows('extension_hooks', $extensionId, "hook_type = 'command'"),
            'routes' => $this->countMetadataRows('extension_routes', $extensionId),
            'panels' => $this->countMetadataRows('extension_panels', $extensionId),
        ];
    }

    private function countMetadataRows(string $table, string $extensionId, ?string $extraCondition = null): int
    {
        $where = 'extension_id = :extension_id';
        if ($extraCondition !== null) {
            $where .= ' AND ' . $extraCondition;
        }

        $statement = $this->connection->prepare(sprintf('SELECT COUNT(*) as aggregate FROM %s WHERE %s', $table, $where));
        $statement->execute(['extension_id' => $extensionId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['aggregate'] ?? 0);
    }

    /**
     * @param array{permissions: int, events: int, commands: int, routes: int, panels: int} $dbCounts
     * @param array{permissions: int, events: int, commands: int, routes: int, panels: int}|null $manifestCounts
     * @return string[]
     */
    private function compareMetadataCounts(array $dbCounts, ?array $manifestCounts): array
    {
        if ($manifestCounts === null) {
            return [];
        }

        $diffs = [];
        foreach ($manifestCounts as $key => $count) {
            $dbCount = $dbCounts[$key] ?? 0;
            if ($dbCount !== $count) {
                $diffs[] = sprintf('%s (manifest=%d, db=%d)', $key, $count, $dbCount);
            }
        }

        return $diffs;
    }

    private function normalizeSignatureStatus(string $status): string
    {
        $status = strtolower($status);
        return in_array($status, ['unknown', 'trusted', 'untrusted'], true) ? $status : 'unknown';
    }

    private function encodeJson(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode manifest metadata payload.', 0, $exception);
        }
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

    private function requireExtension(string $slug): Extension
    {
        $extension = $this->findBySlug($slug);
        if ($extension === null) {
            throw new ExtensionException(sprintf('Extension with slug "%s" not found. Run extensions sync first.', $slug));
        }

        return $extension;
    }

    private function loadLifecycle(Extension $extension): ExtensionLifecycleInterface
    {
        $entryPoint = $this->absolutePath($extension->entryPoint);
        if (!file_exists($entryPoint)) {
            throw new ExtensionException(sprintf('Entry point %s for extension %s was not found.', $entryPoint, $extension->slug));
        }

        $factory = require $entryPoint;
        if ($factory instanceof ExtensionLifecycleInterface) {
            return $factory;
        }

        if (is_callable($factory)) {
            $instance = $factory();
            if ($instance instanceof ExtensionLifecycleInterface) {
                return $instance;
            }
        }

        throw new ExtensionException(sprintf('Extension %s bootstrap must return an ExtensionLifecycleInterface implementation.', $extension->slug));
    }

    private function buildContext(Extension $extension, ?string $organizationId): ExtensionContext
    {
        $hookRegistry = new HookRegistry($this->eventDispatcher, $this->commandRegistry, $extension->slug, $organizationId);

        return new ExtensionContext(
            $extension->id,
            $extension->slug,
            $organizationId,
            $this->connection,
            \app_logger(),
            $this->eventDispatcher,
            $hookRegistry,
            \app_config()
        );
    }

    private function ensureInstalled(Extension $extension, ExtensionLifecycleInterface $lifecycle): Extension
    {
        if ($extension->installedVersion === null) {
            $context = $this->buildContext($extension, null);
            $lifecycle->install($context);
            $this->markInstalled($extension, $extension->version);
            $extension = $this->requireExtension($extension->slug);
        }

        if ($extension->installedVersion !== null && version_compare($extension->installedVersion, $extension->version, '<')) {
            $context = $this->buildContext($extension, null);
            $lifecycle->upgrade($context, $extension->installedVersion, $extension->version);
            $this->markInstalled($extension, $extension->version);
            $extension = $this->requireExtension($extension->slug);
        }

        return $extension;
    }

    private function markInstalled(Extension $extension, string $version): void
    {
        $statement = $this->connection->prepare('UPDATE extensions SET installed_version = :installed_version, status = :status, updated_at = NOW() WHERE id = :id');
        $statement->execute([
            'installed_version' => $version,
            'status' => 'installed',
            'id' => $extension->id,
        ]);
    }

    private function updateStatus(string $extensionId, string $status): void
    {
        $statement = $this->connection->prepare('UPDATE extensions SET status = :status, updated_at = NOW() WHERE id = :id');
        $statement->execute([
            'status' => $status,
            'id' => $extensionId,
        ]);
    }

    private function registerRouteIntents(string $extensionSlug, ?string $organizationId, array $routes): void
    {
        if ($routes === []) {
            return;
        }

        $key = $this->routeKey($extensionSlug, $organizationId);
        $this->routeIntents[$key] = $routes;
    }

    private function clearRouteIntents(string $extensionSlug, ?string $organizationId): void
    {
        $key = $this->routeKey($extensionSlug, $organizationId);
        unset($this->routeIntents[$key]);
    }

    private function routeKey(string $extensionSlug, ?string $organizationId): string
    {
        return $extensionSlug . ':' . ($organizationId ?? '*');
    }

    /**
     * @return array<int, array{surface: string, method: string, path: string, handler: callable, capability: ?string, metadata: array}>
     */
    private function collectRoutes(ExtensionLifecycleInterface $lifecycle, ExtensionContext $context): array
    {
        $lifecycle->routes($context);
        $routes = $context->hooks->routes();
        $context->hooks->clearRoutes();

        return $routes;
    }

    private function absolutePath(string $relative): string
    {
        if (str_starts_with($relative, DIRECTORY_SEPARATOR) || preg_match('#^[A-Za-z]:\\#', $relative) === 1) {
            return $relative;
        }

        return base_path($relative);
    }
}
