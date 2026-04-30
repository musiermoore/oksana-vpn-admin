<?php

namespace App\Models\Concerns;

use App\Models\User;

trait SyncsUserBalance
{
    protected static function bootSyncsUserBalance(): void
    {
        static::created(function ($model) {
            $model->syncRelatedUserBalance();
        });

        static::updated(function ($model) {
            $model->syncRelatedUserBalance();
        });

        static::deleted(function ($model) {
            $model->syncRelatedUserBalance();
        });
    }

    protected function syncRelatedUserBalance(): void
    {
        $userId = $this->user_id ?? null;

        if (! $userId) {
            return;
        }

        $user = User::query()->find($userId);

        if (! $user) {
            return;
        }

        $user->syncStoredBalance();
    }
}
