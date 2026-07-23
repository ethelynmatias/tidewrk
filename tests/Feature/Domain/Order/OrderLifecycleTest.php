<?php

namespace Tests\Feature\Domain\Order;

use App\Domain\Order\Exceptions\InvalidOrderTransitionException;
use App\Domain\Order\Models\Order;
use App\Domain\Order\State\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(): Order
    {
        return Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'total_amount' => 100,
        ]);
    }

    public function test_pending_order_can_be_confirmed(): void
    {
        $order = $this->makeOrder();

        $order->transitionTo(OrderStatus::Confirmed);

        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertNotNull($order->confirmed_at);
        $this->assertSame(1, $order->version);
    }

    public function test_pending_order_cannot_skip_to_completed(): void
    {
        $order = $this->makeOrder();

        $this->expectException(InvalidOrderTransitionException::class);

        $order->transitionTo(OrderStatus::Completed);
    }

    public function test_terminal_states_cannot_transition_anywhere(): void
    {
        $order = $this->makeOrder();
        $order->transitionTo(OrderStatus::Cancelled);

        foreach (OrderStatus::cases() as $target) {
            try {
                $order->transitionTo($target);
                $this->fail("cancelled → {$target->value} should have thrown");
            } catch (InvalidOrderTransitionException) {
                // expected — cancelled is terminal
            }
        }

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_partially_failed_can_recover_to_completed(): void
    {
        $order = $this->makeOrder();
        $order->transitionTo(OrderStatus::Confirmed);
        $order->transitionTo(OrderStatus::PartiallyFailed);

        $order->transitionTo(OrderStatus::Completed);

        $this->assertSame(OrderStatus::Completed, $order->status);
    }

    public function test_partially_failed_cannot_be_reconfirmed(): void
    {
        $order = $this->makeOrder();
        $order->transitionTo(OrderStatus::Confirmed);
        $order->transitionTo(OrderStatus::PartiallyFailed);

        $this->expectException(InvalidOrderTransitionException::class);

        // Retries happen at the STEP level, never by re-confirming the order.
        $order->transitionTo(OrderStatus::Confirmed);
    }
}
