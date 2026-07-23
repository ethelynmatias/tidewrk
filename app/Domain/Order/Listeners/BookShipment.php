<?php

namespace App\Domain\Order\Listeners;

use App\Domain\Order\Events\OrderConfirmed;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderStep;
use App\Domain\Order\Services\OrderFulfillmentService;
use App\Domain\Order\State\OrderStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BookShipment implements ShouldQueue
{
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        private readonly OrderFulfillmentService $fulfillment,
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        $order = Order::findOrFail($event->orderId);
        $step = OrderStep::claim($order, 'shipping');

        if ($step->status->isFinal()) {
            return;
        }

        if ($order->status === OrderStatus::Cancelled) {
            $step->markSkipped();

            return;
        }

        $step->markRunning();

        try {
            $trackingNo = $this->book($order);

            $step->markSucceeded(['external_ref' => $trackingNo]);
        } catch (Throwable $e) {
            $step->markFailed($e);

            throw $e; // let the queue retry with backoff
        }

        $this->fulfillment->evaluateOutcome($order->fresh());
    }

    public function failed(OrderConfirmed $event, Throwable $e): void
    {
        $this->fulfillment->handleStepExhausted($event->orderId, 'shipping');
    }

    /**
     * Fake carrier booking. FULFILLMENT_FAIL_SHIPPING=true simulates a timeout.
     */
    private function book(Order $order): string
    {
        if (config('fulfillment.fail_shipping')) {
            throw new RuntimeException('Carrier booking timed out (simulated).');
        }

        return 'trk_'.Str::uuid();
    }
}
