<?php

namespace App\Domain\Order\Models;

use App\Domain\Order\Exceptions\InvalidOrderTransitionException;
use App\Domain\Order\Exceptions\StaleOrderException;
use App\Domain\Order\State\OrderStatus;
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
     * Defaults mirroring the schema, so a freshly created in-memory model
     * has a real state before its first refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status'  => 'pending',
        'version' => 0,
    ];

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
     * Move the order into a new state. The ONLY way to change status.
     *
     * Two guarantees, both at the domain level:
     *
     * 1. Lifecycle — an illegal transition throws regardless of caller.
     * 2. Concurrency — the UPDATE's WHERE clause pins the status and
     *    version we read (optimistic lock). If a concurrent worker moved
     *    the order first, our write affects zero rows and we throw
     *    instead of silently clobbering their transition.
     *
     * @throws InvalidOrderTransitionException
     * @throws StaleOrderException
     */
    public function transitionTo(OrderStatus $to): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw new InvalidOrderTransitionException($this->status, $to);
        }

        $attributes = [
            'status'     => $to->value,
            'version'    => $this->version + 1,
            'updated_at' => now(),
        ];

        $timestamp = match ($to) {
            OrderStatus::Confirmed => 'confirmed_at',
            OrderStatus::Completed => 'completed_at',
            OrderStatus::Cancelled => 'cancelled_at',
            default                => null,
        };

        if ($timestamp !== null) {
            $attributes[$timestamp] = now();
        }

        $updated = static::whereKey($this->getKey())
            ->where('status', $this->status->value)
            ->where('version', $this->version)
            ->update($attributes);

        if ($updated === 0) {
            throw new StaleOrderException($this->getKey(), $this->version);
        }

        $this->refresh();
    }
}
