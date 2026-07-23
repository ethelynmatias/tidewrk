<?php

namespace App\Domain\Order\Exceptions;

use RuntimeException;

/**
 * Thrown when an optimistic-lock write affects zero rows: another worker
 * changed the order between our read and our write. The caller should
 * re-read the order and decide again — never blindly retry the same write.
 */
class StaleOrderException extends RuntimeException
{
    public function __construct(int $orderId, int $expectedVersion)
    {
        parent::__construct(
            "Order {$orderId} was modified concurrently (expected version {$expectedVersion})."
        );
    }
}
