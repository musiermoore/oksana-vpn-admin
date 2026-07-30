<?php

declare(strict_types=1);

namespace App\DTOs\Server;

use App\DTOs\Data;

class ServerConnectItemSortData extends Data
{
    /**
     * @param  array<int, array{type:string,id:int}>  $items
     */
    public function __construct(
        public array $items,
    ) {}
}
