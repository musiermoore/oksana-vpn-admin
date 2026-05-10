<?php

namespace App\Services;

use App\Models\User;

class UserApiService
{
    public string $telegramId;

    public function __construct(string $telegramId)
    {
        $this->telegramId = trim($telegramId);
    }

    public static function instance(string $telegramId): UserApiService
    {
        return new self($telegramId);
    }

    public function getUser(): ?User
    {
        $query = User::query()
            ->with([
                'configs' => function ($query) {
                    $query->select([
                        'id', 'user_id', 'name'
                    ]);
                },
                'activeSubscription',
            ])
            ->select([
                'users.id',
                'users.telegram',
                'users.name',
                'users.telegram_id',
                'users.balance',
            ])
            ->where('telegram_id', $this->telegramId)
            ->tap(fn ($query) => User::applyBillingSummary($query));

        return $query->first();
    }
}
