<?php

declare(strict_types=1);

namespace App\Extensions\Routing;

use App\Extensions\Contracts\ExtensionRegistryInterface;
use App\Extensions\Exceptions\ExtensionException;
use App\Services\ExtensionSettingsService;
use App\Support\CapabilityAuthorizer;

final class ExtensionRouteDispatcher
{
    private const CSRF_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private readonly ExtensionRegistryInterface $registry,
        private readonly ExtensionSettingsService $settings,
        private readonly CapabilityAuthorizer $authorizer
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function dispatch(string $surface, string $extensionSlug, string $method, string $path, array $options = []): void
    {
        $method = strtoupper($method);
        $organizationId = $options['organization_id'] ?? null;
        $query = $options['query'] ?? $_GET;
        $body = $options['body'] ?? $_POST;
        $headers = $options['headers'] ?? $this->normalizeHeaders($_SERVER);
        $server = $options['server'] ?? $_SERVER;

        if (in_array($surface, ['public', 'api', 'webhook'], true)) {
            if (!is_string($organizationId) || $organizationId === '') {
                throw new ExtensionException('Organization context is required for this route.');
            }

            if (!$this->settings->isEnabled($extensionSlug, $organizationId)) {
                throw new ExtensionException('Extension is not enabled for this organization.');
            }
        }

        $routes = $this->registry->runtimeRoutes($extensionSlug, $organizationId);
        foreach ($routes as $route) {
            if ($route['surface'] !== $surface) {
                continue;
            }

            if ($route['method'] !== $method) {
                continue;
            }

            $params = [];
            if (!$this->pathsMatch($route['path'], $path, $params)) {
                continue;
            }

            $metadata = $route['metadata'] ?? [];
            $capability = $metadata['capability'] ?? null;
            $this->authorizer->authorize($capability);

            if ($this->shouldEnforceCsrf($surface, $method, (bool) ($metadata['csrf'] ?? true))) {
                $token = $body['csrf_token'] ?? $query['csrf_token'] ?? ($options['csrf_token'] ?? null);
                if (!\CSRF::validate($token ?? '')) {
                    throw new ExtensionException('Invalid CSRF token.');
                }
            }

            $request = new ExtensionRouteRequest(
                $method,
                $path,
                $params,
                $query,
                $body,
                $headers,
                $server,
                $organizationId,
                \Auth::id()
            );

            audit_log('extensions.route.dispatch', [
                'extension_slug' => $extensionSlug,
                'surface' => $surface,
                'method' => $method,
                'path' => $path,
                'organization_id' => $organizationId,
                'user_id' => \Auth::id(),
            ]);

            $result = ($route['handler'])($request);
            if ($result === null) {
                return;
            }

            if (is_array($result)) {
                header('Content-Type: application/json');
                echo json_encode($result, JSON_THROW_ON_ERROR);
                return;
            }

            if (is_string($result)) {
                echo $result;
                return;
            }

            return;
        }

        throw new ExtensionException(sprintf('No route matched %s %s for extension %s.', $method, $path, $extensionSlug));
    }

    private function shouldEnforceCsrf(string $surface, string $method, bool $metadataOptIn): bool
    {
        if (!in_array($method, self::CSRF_METHODS, true)) {
            return false;
        }

        if ($surface === 'api') {
            return $metadataOptIn;
        }

        if ($surface === 'webhook') {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function pathsMatch(string $pattern, string $path, array &$params): bool
    {
        $patternSegments = $this->segments($pattern);
        $pathSegments = $this->segments($path);

        if (count($patternSegments) !== count($pathSegments)) {
            return false;
        }

        $params = [];
        foreach ($patternSegments as $index => $segment) {
            $actual = $pathSegments[$index];
            if ($segment === '*') {
                continue;
            }

            if ($segment !== '' && ($segment[0] === '{' && str_ends_with($segment, '}'))) {
                $params[trim($segment, '{}')] = $actual;
                continue;
            }

            if ($segment !== '' && $segment[0] === ':' ) {
                $params[substr($segment, 1)] = $actual;
                continue;
            }

            if ($segment !== $actual) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function segments(string $path): array
    {
        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return [];
        }

        return explode('/', $trimmed);
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private function normalizeHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (!str_starts_with($key, 'HTTP_')) {
                continue;
            }
            $header = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
            $headers[$header] = (string) $value;
        }

        return $headers;
    }
}
