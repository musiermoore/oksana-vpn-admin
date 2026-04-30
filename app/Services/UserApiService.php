<?php

namespace App\Services;

use App\Models\User;

class UserApiService
{
    public string $telegram;

    public function __construct(string $telegram)
    {
        $this->telegram = $telegram;
    }

    public static function instance(string $telegram): UserApiService
    {
        return new self($telegram);
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
            ->whereTelegram('@' . $this->telegram)
            ->tap(fn ($query) => User::applyBillingSummary($query));

        return $query->first();
    }
}
