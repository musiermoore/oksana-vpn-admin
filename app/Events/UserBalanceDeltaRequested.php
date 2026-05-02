<?php

namespace App\Events;

class UserBalanceDeltaRequested
{
    public function __construct(
        public readonly int $userId,
        public readonly float $amount,
    ) {}
}
