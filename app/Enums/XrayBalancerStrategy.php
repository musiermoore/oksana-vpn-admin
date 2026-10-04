<?php

declare(strict_types=1);

namespace App\Enums;

enum XrayBalancerStrategy: string
{
    case RoundRobin = 'roundRobin';
    case LeastPing = 'leastPing';
    case LeastLoad = 'leastLoad';
    case Random = 'random';
}
