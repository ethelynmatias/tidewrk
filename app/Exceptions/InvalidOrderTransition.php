<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use DomainException;

class InvalidOrderTransition extends DomainException
{
    public function __construct(
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
    ) {
        parent::__construct(
            "Illegal order transition: {$from->value} → {$to->value}."
        );
    }
}
