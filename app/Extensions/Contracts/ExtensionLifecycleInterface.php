<?php

declare(strict_types=1);

namespace App\Extensions\Contracts;

use App\Extensions\ExtensionContext;

interface ExtensionLifecycleInterface
{
    public function install(ExtensionContext $context): void;

    public function upgrade(ExtensionContext $context, string $fromVersion, string $toVersion): void;

    public function uninstall(ExtensionContext $context): void;

    public function activate(ExtensionContext $context): void;

    public function deactivate(ExtensionContext $context): void;

    public function routes(ExtensionContext $context): void;
}
