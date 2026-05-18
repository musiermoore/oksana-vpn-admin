<?php

namespace App\DTOs\User;

use App\Models\User;

readonly class ApiUserRegistrationResultData
{
    public function __construct(
        public User $user,
        public bool $created,
    ) {}
}
