<?php

declare(strict_types=1);

use App\Extensions\ExtensionContext;
use App\Extensions\ExtensionRegistry;

return static function (ExtensionContext $context, ExtensionRegistry $registry): void {
    $context->logger->info('GTM extension bootstrap executed.', [
        'extension_id' => $context->extensionId,
        'organization_id' => $context->organizationId,
    ]);
};
