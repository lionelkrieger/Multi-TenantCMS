<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Repositories\OrganizationRepository;

function request_host(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return strtolower(explode(':', $host)[0]);
}

function resolve_organization_from_request(): ?Organization
{
    static $resolved = [];

    $host = request_host();
    $queryOrganizationId = request_query_organization_id();
    $cacheKey = $host . '|' . ($queryOrganizationId ?? 'none');

    if (array_key_exists($cacheKey, $resolved)) {
        return $resolved[$cacheKey];
    }

    $repository = new OrganizationRepository(Database::connection());
    $context = [
        'host' => $host,
        'path' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    ];

    $organization = find_organization_by_host($host, $repository);
    if ($organization !== null) {
        $resolved[$cacheKey] = $organization;
        return $organization;
    }

    if ($queryOrganizationId !== null) {
        $organization = $repository->findById($queryOrganizationId);
        if ($organization !== null) {
            logger('Resolved organization via query parameter fallback.', $context + [
                'source' => 'query_param',
                'organization_id' => $organization->id,
            ]);
            $resolved[$cacheKey] = $organization;
            return $organization;
        }

        logger('Organization query parameter fallback failed.', $context + [
            'source' => 'query_param',
            'organization_id' => $queryOrganizationId,
        ]);
    } else {
        logger('Domain resolution did not match any tenant and no query parameter fallback was provided.', $context);
    }

    $resolved[$cacheKey] = null;
    logger('Unable to resolve organization from request.', $context + ['source' => 'unresolved']);
    return null;
}

function find_organization_by_host(string $host, ?OrganizationRepository $repository = null): ?Organization
{
    $repository ??= new OrganizationRepository(Database::connection());

    $organization = $repository->findByCustomDomain($host);
    if ($organization !== null && $organization->domainVerified) {
        return $organization;
    }

    return null;
}

function request_query_organization_id(): ?string
{
    $candidates = ['org_id', 'org', 'organization_id', 'id'];

    foreach ($candidates as $candidate) {
        if (!isset($_GET[$candidate])) {
            continue;
        }

        $value = trim((string) $_GET[$candidate]);
        if ($value === '') {
            continue;
        }

        if (!preg_match('/^[0-9a-fA-F-]{8,}$/', $value)) {
            continue;
        }

        return $value;
    }

    return null;
}