<?php

declare(strict_types=1);

namespace App\DTOs\UserToken;

use App\DTOs\Data;

class UserTokenData extends Data
{
    public function __construct(
        public int $userId,
    ) {}
}
