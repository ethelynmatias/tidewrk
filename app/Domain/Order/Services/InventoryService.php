<?php

namespace App\Domain\Order\Services;

use App\Domain\Order\Models\Order;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * reservations/releases. FULFILLMENT_FAIL_INVENTORY=true simulates stock-out.
 */
class InventoryService
{
    /** @var array<string, string> reservation ref by idempotency key */
    public array $reservations = [];

    public int $releases = 0;

    public function reserve(Order $order, string $idempotencyKey): string
    {
        if (config('fulfillment.fail_inventory')) {
            throw new RuntimeException('Insufficient stock (simulated).');
        }

        // Same key → same reservation; a retry never holds stock twice.
        return $this->reservations[$idempotencyKey] ??= 'rsv_'.Str::uuid();
    }

    public function release(string $reservationRef): void
    {
        $key = array_search($reservationRef, $this->reservations, strict: true);

        if ($key === false) {
            throw new RuntimeException("Unknown reservation {$reservationRef}.");
        }

        unset($this->reservations[$key]);
        $this->releases++;
    }
}
