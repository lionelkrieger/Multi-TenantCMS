<?php

declare(strict_types=1);

namespace App\Extensions\Events;

final class EventEnvelope
{
    public function __construct(
        public readonly string $event,
        public readonly array $payload = [],
        public readonly array $metadata = []
    ) {
    }
}
