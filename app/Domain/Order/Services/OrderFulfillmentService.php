<?php

namespace App\Domain\Order\Services;

use App\Domain\Order\Enums\StepState;
use App\Domain\Order\Events\OrderConfirmed;
use App\Domain\Order\Exceptions\StaleOrderException;
use App\Domain\Order\Jobs\ReleaseInventoryCompensation;
use App\Domain\Order\Models\Order;
use App\Domain\Order\State\OrderStatus;

class OrderFulfillmentService
{
    /** The steps that must all succeed for an order to complete. */
    public const array STEPS = ['inventory', 'payment', 'shipping'];

    /**
     * Confirm the order and kick off the fulfillment fan-out.
     */
    public function confirm(Order $order): void
    {
        $order->transitionTo(OrderStatus::Confirmed);

        OrderConfirmed::dispatch($order->id);
    }

    /**
     * Called by each listener after its step succeeds: if every step is
     * done, complete the order. Racing workers are resolved by the
     * optimistic lock — one wins the transition, the loser no-ops.
     */
    public function evaluateOutcome(Order $order): void
    {
        // Completion is reachable from Confirmed (happy path) and from
        // PartiallyFailed (a retried step finally succeeded).
        if (! in_array($order->status, [OrderStatus::Confirmed, OrderStatus::PartiallyFailed], true)) {
            return;
        }

        $succeeded = $order->steps()
            ->whereIn('step', self::STEPS)
            ->where('status', StepState::Succeeded->value)
            ->count();

        if ($succeeded === count(self::STEPS)) {
            try {
                $order->transitionTo(OrderStatus::Completed);
            } catch (StaleOrderException) {
                // Another worker completed (or cancelled) it first — fine.
            }
        }
    }

    /**
     * A step exhausted all its queue retries. Mark the order and, where a
     * committed side effect is now orphaned, dispatch its compensation.
     */
    public function handleStepExhausted(int $orderId, string $failedStep): void
    {
        $order = Order::findOrFail($orderId);

        if ($order->status === OrderStatus::Confirmed) {
            try {
                $order->transitionTo(OrderStatus::PartiallyFailed);
            } catch (StaleOrderException) {
                $order->refresh();
            }
        }

        // Payment is unrecoverable but stock may be held → COMPENSATE, don't
        // rollback: the reservation was committed by another worker in its own
        // transaction (possibly minutes ago) — no DB transaction can undo it.
        if ($failedStep === 'payment') {
            ReleaseInventoryCompensation::dispatch($orderId);
        }
    }
}
