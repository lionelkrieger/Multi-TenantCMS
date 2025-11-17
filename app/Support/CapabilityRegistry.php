<?php

declare(strict_types=1);

namespace App\Support;

final class CapabilityRegistry
{
    /** @var array<string, array<int, string>> */
    private array $capabilities;

    public function __construct(?array $capabilities = null)
    {
        $this->capabilities = $this->normalize($capabilities ?? $this->loadFromConfig());
    }

    public function exists(string $capability): bool
    {
        return isset($this->capabilities[$capability]);
    }

    /**
     * @return string[]
     */
    public function all(): array
    {
        return array_keys($this->capabilities);
    }

    /**
     * @param string[] $candidates
     * @return string[] invalid capability names
     */
    public function invalid(array $candidates): array
    {
        $invalid = [];
        foreach ($candidates as $capability) {
            if ($capability === '' || !$this->exists($capability)) {
                $invalid[] = $capability;
            }
        }

        return $invalid;
    }

    /**
     * @return string[]
     */
    public function roles(string $capability): array
    {
        return $this->capabilities[$capability] ?? [];
    }

    /**
     * @param array<string|int, mixed> $values
     * @return array<string, array<int, string>>
     */
    private function normalize(array $values): array
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            if (is_int($key)) {
                if (!is_string($value) || $value === '') {
                    continue;
                }
                $normalized[$value] = ['master_admin'];
                continue;
            }

            if (!is_string($key) || $key === '') {
                continue;
            }

            $roles = $this->normalizeRoles($value);
            $normalized[$key] = $roles === [] ? ['master_admin'] : $roles;
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalizeRoles(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            return $value === '' ? [] : [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $roles = [];
        foreach ($value as $role) {
            if (!is_string($role) || $role === '') {
                continue;
            }
            $roles[] = $role;
        }

        return array_values(array_unique($roles));
    }

    private function loadFromConfig(): array
    {
        $configPath = config_path('capabilities.php');
        if (file_exists($configPath)) {
            $config = require $configPath;
            return is_array($config) ? $config : [];
        }

        return [];
    }
}
