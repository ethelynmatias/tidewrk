<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_number',
    'status',
    'total_amount',
])]
class Order extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status'       => OrderStatus::class,
            'total_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * The execution ledger rows for this order.
     *
     * @return HasMany<OrderStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(OrderStep::class);
    }

    /**
     * Move the order into a new state.
     *
     * Enforces the lifecycle at the domain level: an illegal transition throws
     * regardless of who calls it. Callers are expected to run this inside a
     * transaction with a row lock (see the fulfillment layer) so concurrent
     * workers cannot both apply a transition.
     *
     * @throws InvalidOrderTransition
     */
    public function transitionTo(OrderStatus $to): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw new InvalidOrderTransition($this->status, $to);
        }

        $this->status = $to;
        $this->version++; // optimistic-locking token

        match ($to) {
            OrderStatus::Confirmed => $this->confirmed_at = now(),
            OrderStatus::Completed => $this->completed_at = now(),
            OrderStatus::Cancelled => $this->cancelled_at = now(),
            default                => null,
        };

        $this->save();
    }
}
