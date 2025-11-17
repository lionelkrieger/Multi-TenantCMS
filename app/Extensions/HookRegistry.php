<?php

declare(strict_types=1);

namespace App\Extensions;

use App\Extensions\Commands\CommandRegistry;
use App\Extensions\Events\EventDispatcherInterface;
use App\Extensions\Events\EventEnvelope;
use Closure;

final class HookRegistry
{
    /** @var array<int, array{surface: string, method: string, path: string, handler: callable, capability: ?string, metadata: array}> */
    private array $routes = [];

    public function __construct(
        private readonly EventDispatcherInterface $events,
        private readonly CommandRegistry $commands,
        private readonly string $extensionSlug,
        private readonly ?string $organizationId
    ) {
    }

    public function onEvent(string $event, callable $listener, int $priority = 0): void
    {
        $this->events->listen($event, $listener, $this->extensionSlug, $this->organizationId, $priority);
    }

    public function command(string $name, callable $handler): void
    {
        $this->commands->register($name, $handler, $this->extensionSlug, $this->organizationId);
    }

    public function route(string $surface, string $method, string $path, callable $handler, ?string $capability = null, array $metadata = []): void
    {
        $this->routes[] = [
            'surface' => $surface,
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'capability' => $capability,
            'metadata' => $metadata,
        ];
    }

    /** @return array<int, array{surface: string, method: string, path: string, handler: callable, capability: ?string, metadata: array}> */
    public function routes(): array
    {
        return $this->routes;
    }

    public function clearRoutes(): void
    {
        $this->routes = [];
    }
}
