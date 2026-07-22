<?php

namespace App\Enums;

enum StepStatus: string
{
    case Pending = 'pending';       // recorded, not yet started
    case Running = 'running';       // in progress (a long-running row = stuck)
    case Succeeded = 'succeeded';   // completed successfully
    case Failed = 'failed';         // threw / returned failure
    case Skipped = 'skipped';       // intentionally not run (e.g. order cancelled)
    case Compensated = 'compensated'; // a previous success was rolled back
}
