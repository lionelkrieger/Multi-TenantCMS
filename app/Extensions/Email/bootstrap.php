<?php

declare(strict_types=1);

use App\Extensions\ExtensionContext;
use App\Extensions\ExtensionRegistry;

return static function (ExtensionContext $context, ExtensionRegistry $registry): void {
    // Register email services here.
    $context->logger->info('Email extension bootstrap executed.');
};
