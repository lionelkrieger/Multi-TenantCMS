<?php
// bootstrap.php
declare(strict_types=1);

use App\Extensions\ExtensionContext;
use App\Extensions\ExtensionRegistry;

return static function (ExtensionContext $context, ExtensionRegistry $registry): void {
    // Register services
    $registry->registerService('email.queue', function() use ($context) {
        return new App\Extensions\Email\Services\QueueService($context->connection, $context);
    });

    $registry->registerService('email.documents', function() use ($context) {
        return new App\Extensions\Email\Services\DocumentService(
            $context->connection, 
            $context->storage
        );
    });

    // Listen to reservation events
    $registry->addEventListener('reservation.confirmed', function($event) use ($registry) {
        $emailService = $registry->getService('email.queue');
        $emailService->queueReservationConfirmation(
            $event['organization_id'],
            $event['guest_email'],
            $event['reservation_data']
        );
    });

    $context->logger->info('Email extension loaded with services.');
};