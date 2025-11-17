<?php

declare(strict_types=1);

namespace App\Extensions;

use App\Extensions\Exceptions\ManifestValidationException;
use App\Support\CapabilityRegistry;

final class ManifestValidator
{
    private const ROUTE_SURFACES = ['admin', 'public', 'api', 'webhook'];
    private const HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    private const PANEL_COMPONENTS = ['form', 'toggle', 'custom-view'];
    private const PANEL_VISIBLE_ROLES = ['master_admin', 'org_admin'];

    public function __construct(
        private readonly CapabilityRegistry $capabilities = new CapabilityRegistry()
    ) {
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    public function validate(array $manifest, string $manifestPath): array
    {
        $required = ['slug', 'name', 'display_name', 'version'];
        foreach ($required as $key) {
            if (!isset($manifest[$key]) || !is_string($manifest[$key]) || $manifest[$key] === '') {
                throw new ManifestValidationException(sprintf('Manifest %s missing required field "%s".', $manifestPath, $key));
            }
        }

        $slug = strtolower((string) $manifest['slug']);
        if (!preg_match('/^[a-z0-9]+(?:[\-a-z0-9\/]+)?$/', $slug)) {
            throw new ManifestValidationException(sprintf('Manifest %s has invalid slug "%s".', $manifestPath, $slug));
        }

        $version = (string) $manifest['version'];
        if (!preg_match('/^\d+\.\d+\.\d+(?:[\-+][0-9A-Za-z\.]+)?$/', $version)) {
            throw new ManifestValidationException(sprintf('Manifest %s has invalid version "%s".', $manifestPath, $version));
        }

        $entryPoint = $manifest['entry_point'] ?? 'bootstrap.php';
        if (!is_string($entryPoint) || $entryPoint === '') {
            throw new ManifestValidationException(sprintf('Manifest %s entry_point must be a non-empty string.', $manifestPath));
        }

        $permissions = $this->normalizePermissions($manifest['permissions'] ?? [], $manifestPath);
        $hooks = $this->normalizeHooks($manifest['hooks'] ?? [], $manifestPath);

        return [
            'slug' => $slug,
            'name' => (string) $manifest['name'],
            'display_name' => (string) $manifest['display_name'],
            'version' => $version,
            'description' => isset($manifest['description']) ? (string) $manifest['description'] : null,
            'author' => isset($manifest['author']) ? (string) $manifest['author'] : null,
            'homepage_url' => isset($manifest['homepage_url']) ? (string) $manifest['homepage_url'] : null,
            'entry_point' => $entryPoint,
            'permissions' => $permissions,
            'hooks' => $hooks,
            'requires_core_version' => isset($manifest['requires_core_version']) ? (string) $manifest['requires_core_version'] : null,
            'signature' => $this->normalizeSignature($manifest['signature'] ?? null, $manifestPath),
        ];
    }

    /**
     * @param mixed $permissions
     * @return string[]
     */
    private function normalizePermissions(mixed $permissions, string $manifestPath): array
    {
        if ($permissions === null) {
            return [];
        }

        if (!is_array($permissions)) {
            throw new ManifestValidationException(sprintf('Manifest %s permissions must be an array of strings.', $manifestPath));
        }

        $normalized = [];
        foreach ($permissions as $permission) {
            if (!is_string($permission) || $permission === '') {
                throw new ManifestValidationException(sprintf('Manifest %s contains invalid permission values.', $manifestPath));
            }
            $normalized[] = $permission;
        }

        $invalid = $this->capabilities->invalid($normalized);
        if ($invalid !== []) {
            throw new ManifestValidationException(sprintf('Manifest %s requests unknown capabilities: %s', $manifestPath, implode(', ', $invalid)));
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param mixed $hooks
     * @return array{events: string[], commands: string[], routes: array<int, array{surface: string, method: string, path: string, capability: ?string, metadata: array}>, ui_panels: array<int, array<string, mixed>>}
     */
    private function normalizeHooks(mixed $hooks, string $manifestPath): array
    {
        if ($hooks === null) {
            return [
                'events' => [],
                'commands' => [],
                'routes' => [],
                'ui_panels' => [],
            ];
        }

        if (!is_array($hooks)) {
            throw new ManifestValidationException(sprintf('Manifest %s hooks block must be an object.', $manifestPath));
        }

        $events = $this->normalizeHookNames($hooks['events'] ?? [], 'events', $manifestPath);
        $commands = $this->normalizeHookNames($hooks['commands'] ?? [], 'commands', $manifestPath);
        $routes = $this->normalizeRoutes($hooks['routes'] ?? [], $manifestPath);
        $panels = $this->normalizePanels($hooks['ui_panels'] ?? [], $manifestPath);

        return [
            'events' => $events,
            'commands' => $commands,
            'routes' => $routes,
            'ui_panels' => $panels,
        ];
    }

    /**
     * @param mixed $entries
     * @return string[]
     */
    private function normalizeHookNames(mixed $entries, string $type, string $manifestPath): array
    {
        if ($entries === null) {
            return [];
        }

        if (!is_array($entries)) {
            throw new ManifestValidationException(sprintf('Manifest %s hooks.%s must be an array of strings.', $manifestPath, $type));
        }

        $normalized = [];
        foreach ($entries as $entry) {
            if (!is_string($entry) || $entry === '') {
                throw new ManifestValidationException(sprintf('Manifest %s hooks.%s entries must be non-empty strings.', $manifestPath, $type));
            }
            $normalized[] = $entry;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param mixed $routes
     * @return array<int, array{surface: string, method: string, path: string, capability: ?string, metadata: array}>
     */
    private function normalizeRoutes(mixed $routes, string $manifestPath): array
    {
        if ($routes === null) {
            return [];
        }

        if (!is_array($routes)) {
            throw new ManifestValidationException(sprintf('Manifest %s hooks.routes must be an object with surfaces.', $manifestPath));
        }

        $normalized = [];
        foreach ($routes as $surface => $definitions) {
            if (!is_string($surface) || !in_array($surface, self::ROUTE_SURFACES, true)) {
                throw new ManifestValidationException(sprintf('Manifest %s hooks.routes surface "%s" is not supported.', $manifestPath, (string) $surface));
            }

            if (!is_array($definitions)) {
                throw new ManifestValidationException(sprintf('Manifest %s hooks.routes.%s must be an array.', $manifestPath, $surface));
            }

            foreach ($definitions as $index => $definition) {
                $normalized[] = $this->normalizeRouteDefinition($surface, $definition, $manifestPath, $index);
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $definition
     * @return array{surface: string, method: string, path: string, capability: ?string, metadata: array}
     */
    private function normalizeRouteDefinition(string $surface, mixed $definition, string $manifestPath, int|string $index): array
    {
        $method = null;
        $path = null;
        $capability = null;
        $metadata = [];

        if (is_string($definition)) {
            $parts = preg_split('/\s+/', trim($definition), 2);
            if (count($parts) !== 2) {
                throw new ManifestValidationException(sprintf('Manifest %s hooks.routes.%s[%s] must be in "METHOD /path" format.', $manifestPath, $surface, $index));
            }
            [$method, $path] = $parts;
        } elseif (is_array($definition)) {
            $method = $definition['method'] ?? null;
            $path = $definition['path'] ?? null;
            $capability = $definition['capability'] ?? null;
            $metadata = $definition['metadata'] ?? [];
        } else {
            throw new ManifestValidationException(sprintf('Manifest %s hooks.routes.%s[%s] must be a string or object.', $manifestPath, $surface, $index));
        }

        if (!is_string($method) || !in_array(strtoupper($method), self::HTTP_METHODS, true)) {
            throw new ManifestValidationException(sprintf('Manifest %s hooks.routes.%s[%s] has invalid method.', $manifestPath, $surface, $index));
        }

        if (!is_string($path) || !str_starts_with($path, '/')) {
            throw new ManifestValidationException(sprintf('Manifest %s hooks.routes.%s[%s] path must start with "/".', $manifestPath, $surface, $index));
        }

        $method = strtoupper($method);

        if ($capability !== null) {
            if (!is_string($capability) || $capability === '') {
                throw new ManifestValidationException(sprintf('Manifest %s hooks.routes.%s[%s] capability must be a string.', $manifestPath, $surface, $index));
            }
            if (!$this->capabilities->exists($capability)) {
                throw new ManifestValidationException(sprintf('Manifest %s hooks.routes.%s[%s] references unknown capability "%s".', $manifestPath, $surface, $index, $capability));
            }
        }

        if (!is_array($metadata)) {
            throw new ManifestValidationException(sprintf('Manifest %s hooks.routes.%s[%s] metadata must be an object.', $manifestPath, $surface, $index));
        }

        return [
            'surface' => $surface,
            'method' => $method,
            'path' => $path,
            'capability' => $capability,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param mixed $panels
     * @return array<int, array<string, mixed>>
     */
    private function normalizePanels(mixed $panels, string $manifestPath): array
    {
        if ($panels === null) {
            return [];
        }

        if (!is_array($panels)) {
            throw new ManifestValidationException(sprintf('Manifest %s hooks.ui_panels must be an array.', $manifestPath));
        }

        $normalized = [];
        foreach ($panels as $index => $panel) {
            if (!is_array($panel)) {
                throw new ManifestValidationException(sprintf('Manifest %s hooks.ui_panels[%s] must be an object.', $manifestPath, $index));
            }

            $panelKey = isset($panel['id']) ? (string) $panel['id'] : (string) ($panel['panel_key'] ?? '');
            if ($panelKey === '') {
                throw new ManifestValidationException(sprintf('Manifest %s hooks.ui_panels[%s] requires an id.', $manifestPath, $index));
            }

            $title = isset($panel['title']) ? (string) $panel['title'] : '';
            if ($title === '') {
                throw new ManifestValidationException(sprintf('Manifest %s hooks.ui_panels[%s] requires a title.', $manifestPath, $index));
            }

            $component = isset($panel['component']) ? (string) $panel['component'] : '';
            if (!in_array($component, self::PANEL_COMPONENTS, true)) {
                throw new ManifestValidationException(sprintf('Manifest %s hooks.ui_panels[%s] component must be one of: %s', $manifestPath, $index, implode(', ', self::PANEL_COMPONENTS)));
            }

            $schemaPath = isset($panel['schema']) ? (string) $panel['schema'] : (isset($panel['schema_path']) ? (string) $panel['schema_path'] : null);
            if ($component === 'form' && ($schemaPath === null || $schemaPath === '')) {
                throw new ManifestValidationException(sprintf('Manifest %s hooks.ui_panels[%s] form panels require a schema path.', $manifestPath, $index));
            }

            $panelPermissions = $this->normalizePermissions($panel['permissions'] ?? [], $manifestPath);
            $visibleTo = $this->normalizeVisibleTo($panel['visible_to'] ?? null, $manifestPath, $index);
            $orgToggle = isset($panel['org_toggle']) ? (bool) $panel['org_toggle'] : false;
            $sortOrder = isset($panel['sort_order']) ? (int) $panel['sort_order'] : 0;
            $metadata = isset($panel['metadata']) && is_array($panel['metadata']) ? $panel['metadata'] : [];

            $normalized[] = [
                'panel_key' => $panelKey,
                'title' => $title,
                'component' => $component,
                'schema_path' => $schemaPath,
                'permissions' => $panelPermissions,
                'visible_to' => $visibleTo,
                'org_toggle' => $orgToggle,
                'sort_order' => $sortOrder,
                'metadata' => $metadata,
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $visibleTo
     * @return string[]
     */
    private function normalizeVisibleTo(mixed $visibleTo, string $manifestPath, int|string $index): array
    {
        if ($visibleTo === null) {
            return [];
        }

        if (!is_array($visibleTo)) {
            throw new ManifestValidationException(sprintf('Manifest %s hooks.ui_panels[%s].visible_to must be an array.', $manifestPath, $index));
        }

        $normalized = [];
        foreach ($visibleTo as $role) {
            if (!is_string($role) || !in_array($role, self::PANEL_VISIBLE_ROLES, true)) {
                throw new ManifestValidationException(sprintf('Manifest %s hooks.ui_panels[%s] has invalid visible_to role.', $manifestPath, $index));
            }
            $normalized[] = $role;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param mixed $signature
     * @return array{vendor: ?string, status: string}
     */
    private function normalizeSignature(mixed $signature, string $manifestPath): array
    {
        if ($signature === null) {
            return ['vendor' => null, 'status' => 'unknown'];
        }

        if (!is_array($signature)) {
            throw new ManifestValidationException(sprintf('Manifest %s signature block must be an object.', $manifestPath));
        }

        $vendor = isset($signature['vendor']) ? (string) $signature['vendor'] : null;
        $status = isset($signature['status']) ? (string) $signature['status'] : 'unknown';

        if ($status === '') {
            $status = 'unknown';
        }

        return [
            'vendor' => $vendor,
            'status' => $status,
        ];
    }
}
