<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class UserRepository
{
    public function create(array $attributes): User
    {
        return User::create($attributes);
    }

    public function update(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user->refresh();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function findOrFail(int $id): User
    {
        return User::query()->findOrFail($id);
    }

    public function findByTelegramId(string $telegramId): ?User
    {
        return User::query()
            ->where('telegram_id', trim($telegramId))
            ->first();
    }

    public function findByTelegram(string $telegram): ?User
    {
        return User::query()
            ->where('telegram', trim($telegram))
            ->first();
    }

    public function clearTelegramForOthers(string $telegram, int $exceptUserId): void
    {
        User::query()
            ->where('telegram', trim($telegram))
            ->whereKeyNot($exceptUserId)
            ->update(['telegram' => null]);
    }

    public function clearTelegramIdForOthers(string $telegramId, int $exceptUserId): void
    {
        User::query()
            ->where('telegram_id', trim($telegramId))
            ->whereKeyNot($exceptUserId)
            ->update(['telegram_id' => null]);
    }

    public function findApiUserByTelegramId(string $telegramId): ?User
    {
        return User::query()
            ->with([
                'configs' => function ($query) {
                    $query->select([
                        'id',
                        'user_id',
                        'name',
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
                'users.is_admin',
            ])
            ->where('telegram_id', trim($telegramId))
            ->tap(fn (Builder $query) => User::applyBillingSummary($query))
            ->first();
    }

    public function findActiveApiUserByTelegramId(string $telegramId): ?User
    {
        return User::query()
            ->with([
                'activeSubscription' => function ($query) {
                    $query->select([
                        'user_subscriptions.id',
                        'user_subscriptions.user_id',
                        'user_subscriptions.end_date',
                    ]);
                },
            ])
            ->select([
                'users.id',
                'users.telegram_id',
                'users.balance',
            ])
            ->where('telegram_id', trim($telegramId))
            ->where('users.is_active', true)
            ->whereNull('users.deleted_at')
            ->first();
    }
}
