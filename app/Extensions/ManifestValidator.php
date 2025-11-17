<?php

declare(strict_types=1);

namespace App\Extensions;

use App\Extensions\Exceptions\ManifestValidationException;

final class ManifestValidator
{
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

        $permissions = $manifest['permissions'] ?? [];
        if (!is_array($permissions)) {
            throw new ManifestValidationException(sprintf('Manifest %s permissions must be an array of strings.', $manifestPath));
        }
        foreach ($permissions as $permission) {
            if (!is_string($permission) || $permission === '') {
                throw new ManifestValidationException(sprintf('Manifest %s contains invalid permission values.', $manifestPath));
            }
        }

        $hooks = $manifest['hooks'] ?? [];
        if ($hooks !== [] && !is_array($hooks)) {
            throw new ManifestValidationException(sprintf('Manifest %s hooks block must be an object.', $manifestPath));
        }

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
        ];
    }
}
