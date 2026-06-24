<?php

namespace App\Repositories;

use App\Models\TelegramAppToken;
use App\Models\User;

class TelegramAppTokenRepository
{
    public function createForUser(User $user, array $attributes): TelegramAppToken
    {
        return $user->telegramAppTokens()->create($attributes);
    }

    public function findByTokenHash(string $tokenHash): ?TelegramAppToken
    {
        return TelegramAppToken::query()
            ->with('user')
            ->where('token_hash', $tokenHash)
            ->first();
    }

    public function touchLastUsed(TelegramAppToken $token): void
    {
        $token->forceFill([
            'last_used_at' => now(),
        ])->save();
    }

    public function delete(TelegramAppToken $token): void
    {
        $token->delete();
    }
}
