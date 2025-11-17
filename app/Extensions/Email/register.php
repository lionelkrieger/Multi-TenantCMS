<?php

require_once __DIR__ . '/src/QueueService.php';
require_once __DIR__ . '/src/DocumentService.php';

use App\Extensions\Email\QueueService;
use App\Extensions\Email\DocumentService;

Registry::set('email_queue_service', new QueueService(Database::connection()));
Registry::set('email_document_service', new DocumentService());
