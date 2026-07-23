<?php

namespace App\Domain\Order\Listeners;

use App\Domain\Order\Events\OrderConfirmed;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderStep;
use App\Domain\Order\Services\OrderFulfillmentService;
use App\Domain\Order\Services\PaymentGateway;
use App\Domain\Order\State\OrderStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

class CapturePayment implements ShouldQueue
{
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly OrderFulfillmentService $fulfillment,
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        $order = Order::findOrFail($event->orderId);
        $step = OrderStep::claim($order, 'payment');

        // Idempotency: a duplicate dispatch finds the final row and no-ops.
        if ($step->status->isFinal()) {
            return;
        }

        if ($order->status === OrderStatus::Cancelled) {
            $step->markSkipped();

            return;
        }

        $step->markRunning();

        try {
            // The idempotency key travels to the gateway too — provider-side
            // dedupe means even a crash after capture cannot double-charge.
            $txnId = $this->gateway->capture($order, $step->idempotency_key);

            $step->markSucceeded(['external_ref' => $txnId]);
        } catch (Throwable $e) {
            $step->markFailed($e);

            throw $e; // let the queue retry with backoff
        }

        $this->fulfillment->evaluateOutcome($order->fresh());
    }

    /**
     * All retries exhausted — hand off to the recovery flow, which will
     * compensate the inventory reservation if one was made.
     */
    public function failed(OrderConfirmed $event, Throwable $e): void
    {
        $this->fulfillment->handleStepExhausted($event->orderId, 'payment');
    }
}
