<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case PartiallyFailed = 'partially_failed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * The transitions allowed out of each state.
     *
     * This map is the single source of truth for the order lifecycle —
     * anything not listed here is forbidden.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending         => [self::Confirmed, self::Cancelled],
            self::Confirmed       => [self::Completed, self::PartiallyFailed, self::Cancelled],
            self::PartiallyFailed => [self::Completed, self::Cancelled],
            self::Completed       => [], // terminal
            self::Cancelled       => [], // terminal
        };
    }

    /**
     * Whether this order may move into the given state.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), strict: true);
    }

    /**
     * Terminal states cannot transition anywhere.
     */
    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
