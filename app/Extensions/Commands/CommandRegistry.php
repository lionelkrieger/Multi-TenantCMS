<?php

declare(strict_types=1);

namespace App\Extensions\Commands;

use App\Extensions\Exceptions\ExtensionException;

final class CommandRegistry
{
    /** @var array<string, array{name: string, handler: callable, extension: string, organization_id: string|null}> */
    private array $commands = [];

    public function register(string $name, callable $handler, string $extensionSlug, ?string $organizationId = null): void
    {
        $name = strtolower(trim($name));
        if ($name === '') {
            throw new ExtensionException('Command name cannot be empty.');
        }

        $key = $this->key($name, $organizationId);
        $this->commands[$key] = [
            'name' => $name,
            'handler' => $handler,
            'extension' => $extensionSlug,
            'organization_id' => $organizationId,
        ];
    }

    public function dispatch(string $name, array $payload = [], ?string $organizationId = null): mixed
    {
        $name = strtolower(trim($name));
        $key = $this->key($name, $organizationId);
        if (!isset($this->commands[$key])) {
            throw new ExtensionException(sprintf('Command "%s" is not registered for this context.', $name));
        }

        return ($this->commands[$key]['handler'])($payload);
    }

    public function unregisterByExtension(string $extensionSlug, ?string $organizationId = null): void
    {
        foreach ($this->commands as $key => $command) {
            if ($command['extension'] !== $extensionSlug) {
                continue;
            }

            if ($organizationId !== null && $command['organization_id'] !== $organizationId) {
                continue;
            }

            unset($this->commands[$key]);
        }
    }

    private function key(string $name, ?string $organizationId): string
    {
        return $name . ':' . ($organizationId ?? '*');
    }
}
