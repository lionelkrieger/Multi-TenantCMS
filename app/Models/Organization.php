<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Contracts\Arrayable;

final class Organization implements Arrayable
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $createdBy,
        public readonly ?string $logoUrl,
        public readonly string $primaryColor,
        public readonly string $secondaryColor,
        public readonly string $accentColor,
        public readonly string $fontFamily,
        public readonly bool $showBranding,
        public readonly ?string $customCss,
        public readonly ?string $customDomain,
        public readonly bool $domainVerified,
        public readonly ?string $domainVerificationToken,
        public readonly ?string $domainVerifiedAt,
        public readonly string $sslCertificateStatus,
        public readonly ?string $sslCertificateExpires,
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
            (string) ($record['name'] ?? ''),
            (string) ($record['created_by'] ?? ''),
            isset($record['logo_url']) ? (string) $record['logo_url'] : null,
            (string) ($record['primary_color'] ?? '#0066cc'),
            (string) ($record['secondary_color'] ?? '#f8f9fa'),
            (string) ($record['accent_color'] ?? '#dc3545'),
            (string) ($record['font_family'] ?? 'Roboto, sans-serif'),
            (bool) ($record['show_branding'] ?? true),
            isset($record['custom_css']) ? (string) $record['custom_css'] : null,
            isset($record['custom_domain']) ? (string) $record['custom_domain'] : null,
            (bool) ($record['domain_verified'] ?? false),
            isset($record['domain_verification_token']) ? (string) $record['domain_verification_token'] : null,
            isset($record['domain_verified_at']) ? (string) $record['domain_verified_at'] : null,
            (string) ($record['ssl_certificate_status'] ?? 'none'),
            isset($record['ssl_certificate_expires']) ? (string) $record['ssl_certificate_expires'] : null,
            (string) ($record['created_at'] ?? date('Y-m-d H:i:s')),
            (string) ($record['updated_at'] ?? date('Y-m-d H:i:s'))
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_by' => $this->createdBy,
            'logo_url' => $this->logoUrl,
            'primary_color' => $this->primaryColor,
            'secondary_color' => $this->secondaryColor,
            'accent_color' => $this->accentColor,
            'font_family' => $this->fontFamily,
            'show_branding' => $this->showBranding,
            'custom_css' => $this->customCss,
            'custom_domain' => $this->customDomain,
            'domain_verified' => $this->domainVerified,
            'domain_verification_token' => $this->domainVerificationToken,
            'domain_verified_at' => $this->domainVerifiedAt,
            'ssl_certificate_status' => $this->sslCertificateStatus,
            'ssl_certificate_expires' => $this->sslCertificateExpires,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
