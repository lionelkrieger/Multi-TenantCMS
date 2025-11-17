<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Organization;

final class OrganizationRepository extends Repository
{
    public function findById(string $id): ?Organization
    {
        $record = $this->fetchOne('SELECT * FROM organizations WHERE id = :id LIMIT 1', ['id' => $id]);
        return $record ? Organization::fromArray($record) : null;
    }

    public function findByCustomDomain(string $domain): ?Organization
    {
        $record = $this->fetchOne('SELECT * FROM organizations WHERE custom_domain = :domain LIMIT 1', ['domain' => strtolower($domain)]);
        return $record ? Organization::fromArray($record) : null;
    }

    public function listAll(int $limit = 25, int $offset = 0, ?string $search = null): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $sql = 'SELECT * FROM organizations';
        $params = [];

        if ($search !== null && $search !== '') {
            $sql .= ' WHERE name LIKE :search OR custom_domain LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $sql .= sprintf(' ORDER BY created_at DESC LIMIT %d OFFSET %d', $limit, $offset);
        $records = $this->fetchAll($sql, $params);
        return array_map(static fn (array $record): Organization => Organization::fromArray($record), $records);
    }

    public function create(Organization $organization): void
    {
        $this->insert(
            'INSERT INTO organizations (id, name, created_by, logo_url, primary_color, secondary_color, accent_color, font_family, show_branding, custom_css, custom_domain, domain_verified, domain_verification_token, domain_verified_at, ssl_certificate_status, ssl_certificate_expires, created_at, updated_at) VALUES (:id, :name, :created_by, :logo_url, :primary_color, :secondary_color, :accent_color, :font_family, :show_branding, :custom_css, :custom_domain, :domain_verified, :domain_verification_token, :domain_verified_at, :ssl_certificate_status, :ssl_certificate_expires, :created_at, :updated_at)',
            [
                'id' => $organization->id,
                'name' => $organization->name,
                'created_by' => $organization->createdBy,
                'logo_url' => $organization->logoUrl,
                'primary_color' => $organization->primaryColor,
                'secondary_color' => $organization->secondaryColor,
                'accent_color' => $organization->accentColor,
                'font_family' => $organization->fontFamily,
                'show_branding' => $organization->showBranding,
                'custom_css' => $organization->customCss,
                'custom_domain' => $organization->customDomain,
                'domain_verified' => $organization->domainVerified,
                'domain_verification_token' => $organization->domainVerificationToken,
                'domain_verified_at' => $organization->domainVerifiedAt,
                'ssl_certificate_status' => $organization->sslCertificateStatus,
                'ssl_certificate_expires' => $organization->sslCertificateExpires,
                'created_at' => $organization->createdAt,
                'updated_at' => $organization->updatedAt,
            ]
        );
    }

    public function updateBranding(string $organizationId, array $payload): void
    {
        $fields = array_intersect_key($payload, array_flip([
            'name',
            'logo_url',
            'primary_color',
            'secondary_color',
            'accent_color',
            'font_family',
            'show_branding',
            'custom_css',
        ]));

        if ($fields === []) {
            return;
        }

        $sets = [];
        foreach ($fields as $field => $value) {
            $sets[] = sprintf('%s = :%s', $field, $field);
        }
        $sql = sprintf('UPDATE organizations SET %s, updated_at = NOW() WHERE id = :id', implode(', ', $sets));
        $fields['id'] = $organizationId;
        $this->update($sql, $fields);
    }

    public function countAll(?string $search = null): int
    {
        $sql = 'SELECT COUNT(*) FROM organizations';
        $params = [];

        if ($search !== null && $search !== '') {
            $sql .= ' WHERE name LIKE :search OR custom_domain LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        return (int) ($this->fetchColumn($sql, $params) ?? 0);
    }

    /**
     * @return array<int, Organization>
     */
    public function recent(int $limit = 5): array
    {
        $limit = max(1, min(25, $limit));
        $records = $this->fetchAll(
            sprintf('SELECT * FROM organizations ORDER BY created_at DESC LIMIT %d', $limit)
        );

        return array_map(static fn (array $record): Organization => Organization::fromArray($record), $records);
    }
}
