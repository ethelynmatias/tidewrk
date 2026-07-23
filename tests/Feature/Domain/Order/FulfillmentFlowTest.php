<?php

namespace Tests\Feature\Domain\Order;

use App\Domain\Order\Enums\StepState;
use App\Domain\Order\Events\OrderConfirmed;
use App\Domain\Order\Jobs\ReleaseInventoryCompensation;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\InventoryService;
use App\Domain\Order\Services\OrderFulfillmentService;
use App\Domain\Order\Services\PaymentGateway;
use App\Domain\Order\State\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class FulfillmentFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(): Order
    {
        return Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'total_amount' => 100,
        ]);
    }

    public function test_confirming_an_order_fans_out_and_completes_it(): void
    {
        $order = $this->makeOrder();

        app(OrderFulfillmentService::class)->confirm($order);

        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);

        $steps = $order->steps->keyBy('step');
        $this->assertCount(3, $steps);

        foreach (['inventory', 'payment', 'shipping'] as $step) {
            $this->assertSame(StepState::Succeeded, $steps[$step]->status);
            $this->assertNotNull($steps[$step]->externalRef(), "{$step} should record its external ref");
        }
    }

    public function test_duplicate_event_dispatch_causes_no_duplicate_side_effects(): void
    {
        $order = $this->makeOrder();
        $service = app(OrderFulfillmentService::class);

        $service->confirm($order);

        // The same event accidentally dispatched again.
        OrderConfirmed::dispatch($order->id);

        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertCount(3, $order->steps); // no duplicate ledger rows

        // Exactly one capture and one reservation — never two.
        $this->assertCount(1, app(PaymentGateway::class)->captures);
        $this->assertCount(1, app(InventoryService::class)->reservations);
        $this->assertSame(1, $order->steps->firstWhere('step', 'payment')->attempt);
    }

    public function test_payment_failure_compensates_the_inventory_reservation(): void
    {
        config(['fulfillment.fail_payment' => true]);

        $order = $this->makeOrder();
        $service = app(OrderFulfillmentService::class);
        $inventory = app(InventoryService::class);

        // With the sync driver the listener's throw surfaces here AND its
        // failed() hook fires immediately (sync jobs get no retries), so the
        // whole recovery flow — inventory succeeds, payment exhausts, order
        // partially fails, compensation releases the stock — runs inline.
        try {
            $service->confirm($order);
            $this->fail('CapturePayment should have thrown');
        } catch (RuntimeException) {
            // expected: simulated gateway decline
        }

        $order->refresh();
        $steps = $order->steps->keyBy('step');

        $this->assertSame(StepState::Failed, $steps['payment']->status);
        $this->assertSame(RuntimeException::class, $steps['payment']->error_class);
        $this->assertSame('Payment declined by gateway (simulated).', $steps['payment']->error);
        $this->assertSame(OrderStatus::PartiallyFailed, $order->status);
        $this->assertSame(StepState::Compensated, $order->steps->firstWhere('step', 'inventory')->status);
        $this->assertCount(0, $inventory->reservations); // stock released
        $this->assertSame(1, $inventory->releases);
    }

    public function test_compensation_is_idempotent(): void
    {
        config(['fulfillment.fail_payment' => true]);

        $order = $this->makeOrder();
        $service = app(OrderFulfillmentService::class);
        $inventory = app(InventoryService::class);

        try {
            $service->confirm($order);
        } catch (RuntimeException) {
        }

        $service->handleStepExhausted($order->id, 'payment');

        // The compensation job accidentally runs a second time.
        (new ReleaseInventoryCompensation($order->id))->handle($inventory);

        $this->assertSame(1, $inventory->releases); // still exactly one release
    }

    public function test_failed_step_can_be_retried_without_duplicating_side_effects(): void
    {
        config(['fulfillment.fail_shipping' => true]);

        $order = $this->makeOrder();
        $service = app(OrderFulfillmentService::class);

        try {
            $service->confirm($order);
        } catch (RuntimeException) {
            // expected: simulated carrier timeout
        }

        $this->assertSame(
            StepState::Failed,
            $order->fresh()->steps->firstWhere('step', 'shipping')->status,
        );

        // The outage ends; the queue redelivers the event (a retry).
        config(['fulfillment.fail_shipping' => false]);
        OrderConfirmed::dispatch($order->id);

        $order->refresh();
        $steps = $order->steps->keyBy('step');

        $this->assertSame(StepState::Succeeded, $steps['shipping']->status);
        $this->assertSame(2, $steps['shipping']->attempt); // the retry was counted
        $this->assertSame(OrderStatus::Completed, $order->status);

        // Inventory and payment ran once — the retry did not re-execute them.
        $this->assertCount(1, app(PaymentGateway::class)->captures);
        $this->assertCount(1, app(InventoryService::class)->reservations);
    }
}
