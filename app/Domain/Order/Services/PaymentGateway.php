<?php

namespace App\Domain\Order\Services;

use App\Domain\Order\Models\Order;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * captures. FULFILLMENT_FAIL_PAYMENT=true simulates a hard decline.
 */
class PaymentGateway
{
    /** @var array<string, string> transaction id by idempotency key */
    public array $captures = [];

    public function capture(Order $order, string $idempotencyKey): string
    {
        if (config('fulfillment.fail_payment')) {
            throw new RuntimeException('Payment declined by gateway (simulated).');
        }

        // Gateway-side idempotency: the same key always returns the same
        // transaction — a retried capture never charges twice.
        return $this->captures[$idempotencyKey] ??= 'txn_'.Str::uuid();
    }
}
