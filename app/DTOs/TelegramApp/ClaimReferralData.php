<?php

declare(strict_types=1);

namespace App\DTOs\TelegramApp;

use App\DTOs\Data;

class ClaimReferralData extends Data
{
    public function __construct(
        public string $referral,
    ) {}
}
