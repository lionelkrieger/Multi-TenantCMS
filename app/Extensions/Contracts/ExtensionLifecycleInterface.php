<?php

declare(strict_types=1);

namespace App\Extensions\Contracts;

interface ExtensionLifecycleInterface
{
    public function install(): void;
    public function upgrade(string $fromVersion, string $toVersion): void;
    public function uninstall(): void;
}
