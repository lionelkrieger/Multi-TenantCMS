<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Contracts\Arrayable;

final class Extension implements Arrayable
{
    public function __construct(
        public readonly string $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $displayName,
        public readonly string $version,
        public readonly ?string $author,
        public readonly ?string $description,
        public readonly ?string $homepageUrl,
        public readonly string $entryPoint,
        public readonly string $manifestPath,
    public readonly ?string $manifestChecksum,
    public readonly string $signatureStatus,
    public readonly ?string $signatureVendor,
        public readonly string $status,
        public readonly ?string $installedVersion,
        public readonly bool $allowOrgToggle,
        public readonly ?string $requiresCoreVersion,
        public readonly string $createdAt,
        public readonly string $updatedAt
    ) {
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function fromArray(array $record): self
    {
        return new self(
            (string) ($record['id'] ?? ''),
            (string) ($record['slug'] ?? ''),
            (string) ($record['name'] ?? ''),
            (string) ($record['display_name'] ?? ''),
            (string) ($record['version'] ?? '1.0.0'),
            isset($record['author']) ? (string) $record['author'] : null,
            isset($record['description']) ? (string) $record['description'] : null,
            isset($record['homepage_url']) ? (string) $record['homepage_url'] : null,
            (string) ($record['entry_point'] ?? ''),
            (string) ($record['manifest_path'] ?? ''),
            isset($record['manifest_checksum']) ? (string) $record['manifest_checksum'] : null,
            (string) ($record['signature_status'] ?? 'unknown'),
            isset($record['signature_vendor']) ? (string) $record['signature_vendor'] : null,
            (string) ($record['status'] ?? 'inactive'),
            isset($record['installed_version']) ? (string) $record['installed_version'] : null,
            (bool) ($record['allow_org_toggle'] ?? false),
            isset($record['requires_core_version']) ? (string) $record['requires_core_version'] : null,
            (string) ($record['created_at'] ?? date('Y-m-d H:i:s')),
            (string) ($record['updated_at'] ?? date('Y-m-d H:i:s'))
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'display_name' => $this->displayName,
            'version' => $this->version,
            'author' => $this->author,
            'description' => $this->description,
            'homepage_url' => $this->homepageUrl,
            'entry_point' => $this->entryPoint,
            'manifest_path' => $this->manifestPath,
            'manifest_checksum' => $this->manifestChecksum,
            'signature_status' => $this->signatureStatus,
            'signature_vendor' => $this->signatureVendor,
            'status' => $this->status,
            'installed_version' => $this->installedVersion,
            'allow_org_toggle' => $this->allowOrgToggle,
            'requires_core_version' => $this->requiresCoreVersion,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
