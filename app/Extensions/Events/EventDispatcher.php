<?php

declare(strict_types=1);

namespace App\Extensions\Events;

use App\Extensions\Exceptions\ExtensionException;

final class EventDispatcher implements EventDispatcherInterface
{
    /** @var array<string, array<int, array{id: string, extension: string, organization?: string|null, priority: int, listener: callable}>> */
    private array $listeners = [];

    public function listen(string $event, callable $listener, string $extensionSlug, ?string $organizationId = null, int $priority = 0): string
    {
        if ($event === '') {
            throw new ExtensionException('Event name must be provided when registering a listener.');
        }

        $id = generate_uuid_v4();
        $this->listeners[$event][] = [
            'id' => $id,
            'extension' => $extensionSlug,
            'organization' => $organizationId,
            'priority' => $priority,
            'listener' => $listener,
        ];

        usort($this->listeners[$event], static function (array $a, array $b): int {
            return $b['priority'] <=> $a['priority'];
        });

        return $id;
    }

    public function dispatch(string $event, array $payload = [], array $metadata = []): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }

        $envelope = new EventEnvelope($event, $payload, $metadata);
        foreach ($this->listeners[$event] as $listener) {
            ($listener['listener'])($envelope);
        }
    }

    public function removeListeners(string $extensionSlug, ?string $organizationId = null): void
    {
        foreach ($this->listeners as $event => $listeners) {
            $this->listeners[$event] = array_values(array_filter($listeners, static function (array $listener) use ($extensionSlug, $organizationId): bool {
                if ($listener['extension'] !== $extensionSlug) {
                    return true;
                }

                if ($organizationId === null) {
                    return false;
                }

                return $listener['organization'] !== $organizationId;
            }));

            if ($this->listeners[$event] === []) {
                unset($this->listeners[$event]);
            }
        }
    }
}
