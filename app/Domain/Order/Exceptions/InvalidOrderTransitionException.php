<?php

namespace App\Domain\Order\Exceptions;

use App\Domain\Order\State\OrderStatus;
use DomainException;

class InvalidOrderTransitionException extends DomainException
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
