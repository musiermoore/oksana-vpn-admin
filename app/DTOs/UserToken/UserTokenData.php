<?php

namespace App\DTOs\UserToken;

readonly class UserTokenData
{
    public function __construct(
        public int $userId,
    ) {}
}
