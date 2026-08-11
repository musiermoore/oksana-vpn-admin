<?php

declare(strict_types=1);

namespace App\DTOs\Giveaway;

use App\DTOs\Data;

class GiveawayData extends Data
{
    /**
     * @param array<int, GiveawayPrizeData> $prizes
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public bool $adminsOnly,
        public string $startsAt,
        public string $endsAt,
        public bool $autoRepeatEnabled,
        public int $repeatDelayMinutes,
        public ?int $repeatLimit,
        public array $prizes = [],
    ) {}
}
