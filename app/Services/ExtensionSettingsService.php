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
        $row = $this->fetchSettingsRow($extensionSlug, $organizationId);
        if ($row === null) {
            return $default;
        }

        $settings = $this->normalizeSettings($row['settings'] ?? []);
        if (!array_key_exists($key, $settings)) {
            return $default;
        }

        $payload = $settings[$key];
        $value = $payload['value'] ?? null;
        $encrypted = (bool) ($payload['encrypted'] ?? false);
        $serialized = (bool) ($payload['serialized'] ?? false);

        if ($encrypted && is_string($value)) {
            $value = Encryptor::decrypt($value);
        }

        if ($serialized && is_string($value)) {
            $value = $this->decodeValue($value);
        }

        return $value ?? $default;
    }

    public function set(string $extensionSlug, string $organizationId, string $key, mixed $value, bool $encrypt = false): void
    {
        $row = $this->fetchSettingsRow($extensionSlug, $organizationId, true);
        $settings = $this->normalizeSettings($row['settings'] ?? []);

        $isSerialized = !is_scalar($value) && $value !== null;
        $storedValue = $isSerialized ? $this->encodeValue($value) : $value;

        $isEncrypted = false;
        if ($encrypt && $storedValue !== null) {
            $storedValue = Encryptor::encrypt((string) $storedValue);
            $isEncrypted = true;
        }

        $settings[$key] = [
            'value' => $storedValue,
            'encrypted' => $isEncrypted,
            'serialized' => $isSerialized,
            'updated_at' => date(DATE_ATOM),
        ];

        $this->persistSettings((string) $row['id'], $settings);
    }

    public function all(string $extensionSlug, string $organizationId): array
    {
        $row = $this->fetchSettingsRow($extensionSlug, $organizationId);
        if ($row === null) {
            return [];
        }

        $settings = $this->normalizeSettings($row['settings']);
        $decoded = [];
        foreach ($settings as $key => $payload) {
            $value = $payload['value'] ?? null;
            $encrypted = (bool) ($payload['encrypted'] ?? false);
            $serialized = (bool) ($payload['serialized'] ?? false);

            if ($encrypted && is_string($value)) {
                $value = Encryptor::decrypt($value);
            }

            if ($serialized && is_string($value)) {
                $value = $this->decodeValue($value);
            }

            $decoded[$key] = $value;
        }

        return $decoded;
    }

    public function setEnabled(string $extensionSlug, string $organizationId, bool $enabled): void
    {
        $row = $this->fetchSettingsRow($extensionSlug, $organizationId, true);
        $statement = $this->connection->prepare('UPDATE extension_settings SET enabled = :enabled, updated_at = NOW() WHERE id = :id');
        $statement->execute([
            'enabled' => $enabled ? 1 : 0,
            'id' => $row['id'],
        ]);
    }

    public function isEnabled(string $extensionSlug, string $organizationId): bool
    {
        $row = $this->fetchSettingsRow($extensionSlug, $organizationId);
        return $row !== null && (bool) ($row['enabled'] ?? false);
    }

    /**
     * @return array{enabled: bool, settings: array<string, mixed>}
     */
    public function status(string $extensionSlug, string $organizationId): array
    {
        $row = $this->fetchSettingsRow($extensionSlug, $organizationId);
        return [
            'enabled' => $row !== null && (bool) ($row['enabled'] ?? false),
            'settings' => $this->all($extensionSlug, $organizationId),
        ];
    }

    /**
     * @return array{id: string, extension_id: string, organization_id: string, settings: mixed, enabled?: bool}|null
     */
    private function fetchSettingsRow(string $extensionSlug, string $organizationId, bool $createIfMissing = false): ?array
    {
        $extensionId = $this->getExtensionId($extensionSlug);

        $statement = $this->connection->prepare('SELECT * FROM extension_settings WHERE extension_id = :extension_id AND organization_id = :organization_id LIMIT 1');
        $statement->execute([
            'extension_id' => $extensionId,
            'organization_id' => $organizationId,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            return $row;
        }

        if (!$createIfMissing) {
            return null;
        }

        $id = generate_uuid_v4();
        $insert = $this->connection->prepare('INSERT INTO extension_settings (id, extension_id, organization_id, settings, enabled, created_at, updated_at) VALUES (:id, :extension_id, :organization_id, :settings, 0, NOW(), NOW())');
        $insert->execute([
            'id' => $id,
            'extension_id' => $extensionId,
            'organization_id' => $organizationId,
            'settings' => '{}',
        ]);

        return [
            'id' => $id,
            'extension_id' => $extensionId,
            'organization_id' => $organizationId,
            'settings' => '{}',
            'enabled' => 0,
        ];
    }

    private function persistSettings(string $id, array $settings): void
    {
        try {
            $encoded = json_encode($settings, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode extension settings payload.', 0, $exception);
        }

        $statement = $this->connection->prepare('UPDATE extension_settings SET settings = :settings, updated_at = NOW() WHERE id = :id');
        $statement->execute([
            'settings' => $encoded,
            'id' => $id,
        ]);
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

    private function normalizeSettings(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (!is_string($raw) || $raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            logger('Failed to decode extension settings JSON', ['error' => $exception->getMessage()]);
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $key => $value) {
            if (is_array($value) && array_key_exists('value', $value)) {
                $normalized[$key] = $value + [
                    'encrypted' => (bool) ($value['encrypted'] ?? false),
                    'serialized' => (bool) ($value['serialized'] ?? false),
                ];
                continue;
            }

            $normalized[$key] = [
                'value' => $value,
                'encrypted' => false,
                'serialized' => !is_scalar($value) && $value !== null,
            ];
        }

        return $normalized;
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
