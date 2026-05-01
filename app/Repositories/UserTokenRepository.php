<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\UserToken;

class UserTokenRepository
{
    public function createForUser(User $user, array $attributes): UserToken
    {
        return $user->tokens()->create($attributes);
    }

    public function delete(UserToken $userToken): void
    {
        $userToken->delete();
    }
}
