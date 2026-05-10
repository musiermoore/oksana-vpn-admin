<?php

namespace App\Http\Resources;

use App\Models\UserToken;

class UserTokenResource
{
    public static function make(UserToken $userToken): array
    {
        return [
            'id' => $userToken->id,
            'token' => $userToken->token,
            'password' => $userToken->password,
            'expires_at' => $userToken->expires_at,
            'user' => $userToken->user ? [
                'id' => $userToken->user->id,
                'name' => $userToken->user->name,
                'telegram' => $userToken->user->telegram,
                'full_name' => $userToken->user->full_name,
            ] : null,
            'links' => [
                'show' => route('user-tokens.show', $userToken),
                'destroy' => route('user-tokens.destroy', $userToken),
                'public_configs' => route('users.configs', $userToken->token),
            ],
        ];
    }
}
