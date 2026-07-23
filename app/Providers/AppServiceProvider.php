<?php

namespace App\Providers;

use App\Domain\Order\Events\OrderConfirmed;
use App\Domain\Order\Listeners\BookShipment;
use App\Domain\Order\Listeners\CapturePayment;
use App\Domain\Order\Listeners\ReserveInventory;
use App\Domain\Order\Services\InventoryService;
use App\Domain\Order\Services\PaymentGateway;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singletons so the fake external systems keep in-memory state
        // (reservations, captures) across a request/test.
        $this->app->singleton(PaymentGateway::class);
        $this->app->singleton(InventoryService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Domain listeners live under App\Domain, outside the auto-discovery
        // path (app/Listeners), so the fan-out is wired explicitly.
        Event::listen(OrderConfirmed::class, ReserveInventory::class);
        Event::listen(OrderConfirmed::class, CapturePayment::class);
        Event::listen(OrderConfirmed::class, BookShipment::class);
    }
}
