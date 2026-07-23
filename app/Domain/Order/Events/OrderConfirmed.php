<?php

namespace App\Domain\Order\Events;

use Illuminate\Foundation\Events\Dispatchable;

class OrderConfirmed
{
    use Dispatchable;

    /**
     * Carries the id, NOT the model — a queued listener must re-read fresh
     * state (the order may have been cancelled while the event sat in the queue).
     */
    public function __construct(
        public readonly int $orderId,
    ) {}
}
