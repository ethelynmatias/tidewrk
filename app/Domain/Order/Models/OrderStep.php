<?php

namespace App\Domain\Order\Models;

use App\Domain\Order\Enums\StepState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

#[Fillable([
    'order_id',
    'step',
    'status',
    'attempt',
    'idempotency_key',
    'error_class',
    'error',
    'payload',
    'started_at',
    'finished_at',
])]
class OrderStep extends Model
{
    /**
     * Defaults mirroring the schema, so a freshly claimed in-memory row
     * has a real state before its first refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status'  => 'pending',
        'attempt' => 1,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status'      => StepState::class,
            'payload'     => 'array',
            'started_at'  => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * The order this step belongs to.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Atomically claim the ledger row for a step.
     *
     * The deterministic idempotency key plus its unique constraint means a
     * double-dispatched event or a duplicate worker finds the existing row
     * instead of creating a second one — the DB enforces the dedupe, not us.
     */
    public static function claim(Order $order, string $step): self
    {
        return static::firstOrCreate(
            ['idempotency_key' => "{$order->id}:{$step}"],
            ['order_id' => $order->id, 'step' => $step, 'status' => StepState::Pending],
        );
    }

    public function markRunning(): void
    {
        $this->update([
            'status'     => StepState::Running,
            // A re-run after a recorded failure is a retry — count it.
            'attempt'    => $this->status === StepState::Failed ? $this->attempt + 1 : $this->attempt,
            'started_at' => now(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload External refs worth persisting (txn id, reservation id, …)
     */
    public function markSucceeded(array $payload = []): void
    {
        $this->update([
            'status'      => StepState::Succeeded,
            'payload'     => array_merge($this->payload ?? [], $payload),
            'error_class' => null,
            'error'       => null,
            'finished_at' => now(),
        ]);
    }

    public function markFailed(Throwable $e): void
    {
        $this->update([
            'status'      => StepState::Failed,
            'error_class' => $e::class,
            'error'       => $e->getMessage(),
            'finished_at' => now(),
        ]);
    }

    public function markSkipped(): void
    {
        $this->update(['status' => StepState::Skipped, 'finished_at' => now()]);
    }

    public function markCompensated(): void
    {
        $this->update(['status' => StepState::Compensated, 'finished_at' => now()]);
    }

    /**
     * The external system's reference for this step's side effect
     * (payment txn id, inventory reservation id, tracking number).
     */
    public function externalRef(): ?string
    {
        return $this->payload['external_ref'] ?? null;
    }
}
