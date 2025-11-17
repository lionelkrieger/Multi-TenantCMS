<?php

declare(strict_types=1);

use App\Extensions\Contracts\ExtensionLifecycleInterface;
use App\Extensions\Events\EventEnvelope;
use App\Extensions\ExtensionContext;

return new class implements ExtensionLifecycleInterface {
    public function install(ExtensionContext $context): void
    {
        $context->logger->info('GTM install executed.', ['extension_id' => $context->extensionId]);
    }

    public function upgrade(ExtensionContext $context, string $fromVersion, string $toVersion): void
    {
        $context->logger->info('GTM upgrade executed.', ['from' => $fromVersion, 'to' => $toVersion]);
    }

    public function uninstall(ExtensionContext $context): void
    {
        $context->logger->info('GTM uninstall executed.', ['extension_id' => $context->extensionId]);
    }

    public function activate(ExtensionContext $context): void
    {
        $context->logger->info('GTM activated for organization.', ['organization_id' => $context->organizationId]);

        $context->hooks->onEvent('reservation.created', function (EventEnvelope $event) use ($context): void {
            $context->logger->info('GTM data layer event registered.', [
                'organization_id' => $context->organizationId,
                'payload' => $event->payload,
            ]);
        });
    }

    public function deactivate(ExtensionContext $context): void
    {
        $context->logger->info('GTM deactivated for organization.', ['organization_id' => $context->organizationId]);
    }
};
