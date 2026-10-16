<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final class HealthCheckUserService
{
    public function get(): User
    {
        $user = User::withTrashed()
            ->where('is_health_check_user', true)
            ->first();

        if ($user === null) {
            try {
                $user = User::query()->forceCreate([
                    'name' => '@OksanaVpnDebugBot',
                    'telegram' => '@OksanaVpnDebugBot',
                    'telegram_id' => '000000000',
                    'uuid' => (string) Str::uuid(),
                    'description' => 'System account used only for automated subscription health checks.',
                    'is_admin' => false,
                    'is_active' => false,
                    'is_health_check_user' => true,
                ]);
            } catch (QueryException $exception) {
                $user = User::withTrashed()->where('is_health_check_user', true)->first();

                if ($user === null) {
                    throw $exception;
                }
            }
        }

        if ($user->trashed()) {
            $user->restore();
        }

        $user->forceFill([
            'name' => '@OksanaVpnDebugBot',
            'telegram' => '@OksanaVpnDebugBot',
            'telegram_id' => '000000000',
            'description' => 'System account used only for automated subscription health checks.',
            'is_admin' => false,
            'is_active' => false,
            'is_health_check_user' => true,
        ])->save();

        return $user->refresh();
    }
}
