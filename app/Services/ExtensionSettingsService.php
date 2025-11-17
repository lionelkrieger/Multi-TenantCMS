<?php

declare(strict_types=1);

namespace App\Services;

use App\Extensions\Contracts\ExtensionSettingsStoreInterface;
use App\Support\Encryptor;
use JsonException;
use PDO;
use RuntimeException;

final class ExtensionSettingsService implements ExtensionSettingsStoreInterface
{
    public const ENABLED_KEY = 'core.enabled';

    private PDO $connection;

    /**
     * @var array<string, string>
     */
    private array $extensionIdCache = [];

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? \Database::connection();
    }

    public function get(string $extensionSlug, string $organizationId, string $key, mixed $default = null): mixed
    {
        $extensionId = $this->getExtensionId($extensionSlug);
        $storageKey = $this->normalizeStorageKey($extensionSlug, $key);
        $row = $this->fetchSetting($extensionId, $organizationId, $storageKey);
        if ($row === null && $storageKey !== $key) {
            // Legacy fallback for pre-namespaced keys
            $row = $this->fetchSetting($extensionId, $organizationId, $key);
        }
        if ($row === null) {
            return $default;
        }

        $value = $this->unpackPayload($row['value']);
        return $value ?? $default;
    }

    public function set(string $extensionSlug, string $organizationId, string $key, mixed $value, bool $encrypt = false): void
    {
    $extensionId = $this->getExtensionId($extensionSlug);
    $storageKey = $this->normalizeStorageKey($extensionSlug, $key);
    $payload = $this->preparePayload($value, $encrypt);

        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode extension setting payload.', 0, $exception);
        }

        $statement = $this->connection->prepare(
            'INSERT INTO extension_settings (id, extension_id, organization_id, `key`, `value`, created_at, updated_at)
             VALUES (:id, :extension_id, :organization_id, :key, :value, NOW(), NOW())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()'
        );

        $statement->execute([
            'id' => generate_uuid_v4(),
            'extension_id' => $extensionId,
            'organization_id' => $organizationId,
            'key' => $storageKey,
            'value' => $encoded,
        ]);
    }

    public function all(string $extensionSlug, string $organizationId): array
    {
        $extensionId = $this->getExtensionId($extensionSlug);
        $statement = $this->connection->prepare('SELECT `key`, `value` FROM extension_settings WHERE extension_id = :extension_id AND organization_id = :organization_id');
        $statement->execute([
            'extension_id' => $extensionId,
            'organization_id' => $organizationId,
        ]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $settings = [];
        $prefix = $this->keyPrefix($extensionSlug);
        foreach ($rows as $row) {
            $storedKey = (string) $row['key'];
            $key = $this->displayKey($prefix, $storedKey);
            $value = $this->unpackPayload($row['value']);
            $settings[$key] = $value;
        }

        return $settings;
    }

    public function setEnabled(string $extensionSlug, string $organizationId, bool $enabled): void
    {
        $this->set($extensionSlug, $organizationId, self::ENABLED_KEY, $enabled);
    }

    public function isEnabled(string $extensionSlug, string $organizationId): bool
    {
        return (bool) $this->get($extensionSlug, $organizationId, self::ENABLED_KEY, false);
    }

    /**
     * @return array{enabled: bool, settings: array<string, mixed>}
     */
    public function status(string $extensionSlug, string $organizationId): array
    {
        $all = $this->all($extensionSlug, $organizationId);
        $enabled = (bool) ($all[self::ENABLED_KEY] ?? false);
        unset($all[self::ENABLED_KEY]);

        return [
            'enabled' => $enabled,
            'settings' => $all,
        ];
    }

    private function getExtensionId(string $slug): string
    {
        if (isset($this->extensionIdCache[$slug])) {
            return $this->extensionIdCache[$slug];
        }

        $statement = $this->connection->prepare('SELECT id FROM extensions WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $extension = $statement->fetch(PDO::FETCH_ASSOC);
        if ($extension === false) {
            throw new RuntimeException(sprintf('Extension with slug "%s" does not exist.', $slug));
        }

        $extensionId = (string) $extension['id'];
        $this->extensionIdCache[$slug] = $extensionId;

        return $extensionId;
    }

    private function fetchSetting(string $extensionId, string $organizationId, string $key): ?array
    {
        $statement = $this->connection->prepare('SELECT `value` FROM extension_settings WHERE extension_id = :extension_id AND organization_id = :organization_id AND `key` = :key LIMIT 1');
        $statement->execute([
            'extension_id' => $extensionId,
            'organization_id' => $organizationId,
            'key' => $key,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    private function preparePayload(mixed $value, bool $encrypt): array
    {
        $isSerialized = !is_scalar($value) && $value !== null;
        $storedValue = $isSerialized ? $this->encodeValue($value) : $value;

        $isEncrypted = false;
        if ($encrypt && $storedValue !== null) {
            $storedValue = Encryptor::encrypt((string) $storedValue);
            $isEncrypted = true;
        }

        return [
            'value' => $storedValue,
            'encrypted' => $isEncrypted,
            'serialized' => $isSerialized,
            'updated_at' => date(DATE_ATOM),
        ];
    }

    private function unpackPayload(mixed $raw): mixed
    {
        if (is_string($raw) && $raw !== '') {
            try {
                $raw = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                logger('Failed to decode extension setting payload', ['error' => $exception->getMessage()]);
                return null;
            }
        }

        if (!is_array($raw)) {
            return $raw;
        }

        $value = $raw['value'] ?? null;
        $encrypted = (bool) ($raw['encrypted'] ?? false);
        $serialized = (bool) ($raw['serialized'] ?? false);

        if ($encrypted && is_string($value)) {
            $value = Encryptor::decrypt($value);
        }

        if ($serialized && is_string($value)) {
            $value = $this->decodeValue($value);
        }

        return $value;
    }

    private function normalizeStorageKey(string $extensionSlug, string $key): string
    {
        if ($this->isGlobalKey($key)) {
            return $key;
        }

        $key = ltrim($key);
        $prefix = $this->keyPrefix($extensionSlug);
        if (str_starts_with($key, $prefix . '.')) {
            return $key;
        }

        return sprintf('%s.%s', $prefix, $key);
    }

    private function displayKey(string $prefix, string $storedKey): string
    {
        if ($this->isGlobalKey($storedKey)) {
            return $storedKey;
        }

        $prefixed = $prefix . '.';
        if (str_starts_with($storedKey, $prefixed)) {
            return substr($storedKey, strlen($prefixed));
        }

        return $storedKey;
    }

    private function keyPrefix(string $extensionSlug): string
    {
        return str_replace(['/', ' '], '.', strtolower($extensionSlug));
    }

    private function isGlobalKey(string $key): bool
    {
        return str_starts_with($key, 'core.');
    }

    private function encodeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode complex settings value.', 0, $exception);
        }
    }

    private function decodeValue(string $value): mixed
    {
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            logger('Failed to decode serialized setting value', ['error' => $exception->getMessage()]);
            return null;
        }
    }
}
