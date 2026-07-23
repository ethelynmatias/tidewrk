<?php

namespace App\Domain\Order\Listeners;

use App\Domain\Order\Events\OrderConfirmed;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderStep;
use App\Domain\Order\Services\InventoryService;
use App\Domain\Order\Services\OrderFulfillmentService;
use App\Domain\Order\State\OrderStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

class ReserveInventory implements ShouldQueue
{
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        private readonly InventoryService $inventory,
        private readonly OrderFulfillmentService $fulfillment,
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        $order = Order::findOrFail($event->orderId);
        $step = OrderStep::claim($order, 'inventory');

        // Idempotency: succeeded/skipped/compensated rows never run again.
        if ($step->status->isFinal()) {
            return;
        }

        // The order was cancelled while this sat in the queue → record why
        // nothing happened instead of silently returning.
        if ($order->status === OrderStatus::Cancelled) {
            $step->markSkipped();

            return;
        }

        $step->markRunning();

        try {
            $ref = $this->inventory->reserve($order, $step->idempotency_key);

            $step->markSucceeded(['external_ref' => $ref]);
        } catch (Throwable $e) {
            $step->markFailed($e);

            throw $e; // let the queue retry with backoff
        }

        $this->fulfillment->evaluateOutcome($order->fresh());
    }

    /**
     * All retries exhausted — hand off to the recovery flow.
     */
    public function failed(OrderConfirmed $event, Throwable $e): void
    {
        $this->fulfillment->handleStepExhausted($event->orderId, 'inventory');
    }
}
