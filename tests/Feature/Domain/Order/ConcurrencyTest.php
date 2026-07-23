<?php

namespace Tests\Feature\Domain\Order;

use App\Domain\Order\Enums\StepState;
use App\Domain\Order\Events\OrderConfirmed;
use App\Domain\Order\Exceptions\StaleOrderException;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderStep;
use App\Domain\Order\Services\InventoryService;
use App\Domain\Order\Services\PaymentGateway;
use App\Domain\Order\State\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(): Order
    {
        return Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'total_amount' => 100,
        ]);
    }

    public function test_concurrent_workers_cannot_both_transition_the_same_order(): void
    {
        $order = $this->makeOrder();

        // Two workers read the same order (same version) at the same time.
        $workerA = Order::find($order->id);
        $workerB = Order::find($order->id);

        $workerA->transitionTo(OrderStatus::Confirmed); // A wins the race

        // B still believes the order is pending at version 0 — its write
        // matches zero rows and throws instead of clobbering A's transition.
        $this->expectException(StaleOrderException::class);
        $workerB->transitionTo(OrderStatus::Cancelled);
    }

    public function test_duplicate_step_claims_share_one_ledger_row(): void
    {
        $order = $this->makeOrder();

        $first = OrderStep::claim($order, 'payment');
        $second = OrderStep::claim($order, 'payment'); // duplicate worker

        $this->assertTrue($first->is($second));
        $this->assertSame(1, OrderStep::where('order_id', $order->id)->count());
    }

    public function test_queued_event_arriving_after_cancellation_is_skipped(): void
    {
        $order = $this->makeOrder();
        $order->transitionTo(OrderStatus::Cancelled);

        // The OrderConfirmed event was queued before cancellation and is
        // only delivered now.
        OrderConfirmed::dispatch($order->id);

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status); // untouched

        // Every step recorded WHY it did nothing — not silently absent.
        $this->assertCount(3, $order->steps);
        foreach ($order->steps as $step) {
            $this->assertSame(StepState::Skipped, $step->status);
        }

        // And no external side effects fired.
        $this->assertCount(0, app(PaymentGateway::class)->captures);
        $this->assertCount(0, app(InventoryService::class)->reservations);
    }
}
