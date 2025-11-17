<?php

declare(strict_types=1);

namespace App\Extensions\Routing;

final class ExtensionRouteRequest
{
    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $server
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $params,
        public readonly array $query,
        public readonly array $body,
        public readonly array $headers,
        public readonly array $server,
        public readonly ?string $organizationId,
        public readonly ?string $userId
    ) {
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }
}
