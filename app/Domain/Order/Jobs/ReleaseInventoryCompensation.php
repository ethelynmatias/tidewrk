<?php

namespace App\Domain\Order\Jobs;

use App\Domain\Order\Enums\StepState;
use App\Domain\Order\Models\OrderStep;
use App\Domain\Order\Services\InventoryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Compensating action: release the inventory reservation of an order whose
 * payment failed permanently. Queued (it calls an external system and must
 * survive retries) and idempotent (running it twice releases nothing twice).
 */
class ReleaseInventoryCompensation implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly int $orderId,
    ) {}

    public function handle(InventoryService $inventory): void
    {
        $step = OrderStep::query()
            ->where('idempotency_key', "{$this->orderId}:inventory")
            ->first();

        // Nothing was reserved, or it was already compensated — releasing
        // twice would corrupt stock counts, so this must be a no-op.
        if ($step?->status !== StepState::Succeeded) {
            return;
        }

        if ($ref = $step->externalRef()) {
            $inventory->release($ref);
        }

        $step->markCompensated();
    }
}
