<?php

namespace App\Domain\Order\Enums;

enum StepState: string
{
    case Pending = 'pending';         // recorded, not yet started
    case Running = 'running';         // in progress (a long-running row = stuck)
    case Succeeded = 'succeeded';     // completed successfully
    case Failed = 'failed';           // threw / returned failure
    case Skipped = 'skipped';         // intentionally not run (e.g. order cancelled)
    case Compensated = 'compensated'; // a previous success was rolled back

    /**
     * States in which the step must never run (again).
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Succeeded, self::Skipped, self::Compensated], strict: true);
    }
}
