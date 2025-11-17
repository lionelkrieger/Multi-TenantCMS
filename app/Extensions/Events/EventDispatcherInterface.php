<?php

declare(strict_types=1);

namespace App\Extensions\Events;

interface EventDispatcherInterface
{
    public function listen(string $event, callable $listener, string $extensionSlug, ?string $organizationId = null, int $priority = 0): string;

    public function dispatch(string $event, array $payload = [], array $metadata = []): void;

    public function removeListeners(string $extensionSlug, ?string $organizationId = null): void;
}
