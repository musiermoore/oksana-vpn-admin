<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActiveConnection;
use App\Models\BlockedConfig;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeviceLimitService
{
    public function __construct(
        private readonly XrayClientAccessService $clientAccess,
        private readonly SubscriptionMetadataService $metadataService,
    ) {}

    public function enforceForUser(int $userId): void
    {
        $lock = Cache::lock('device-limit:user:'.$userId, 60);

        $lock->block(5, function () use ($userId): void {
            $this->enforceLocked($userId);
        });
    }

    private function enforceLocked(int $userId): void
    {
        $user = User::query()->find($userId);

        if (! $user || (int) $user->max_devices <= 0) {
            return;
        }

        $activeConnections = ActiveConnection::query()
            ->where('user_id', $user->id)
            ->active()
            ->orderByDesc('first_seen')
            ->orderByDesc('id')
            ->get();
        $activeConnectionCount = $activeConnections->count();

        if ($activeConnectionCount <= (int) $user->max_devices) {
            return;
        }

        $candidate = $this->firstUnblockedConnection($user, $activeConnections);

        if (! $candidate instanceof ActiveConnection) {
            return;
        }

        $this->persistBlock($user, $candidate, $activeConnectionCount);
        $this->clientAccess->disable($candidate->config_type, (int) $candidate->config_id);
        $this->metadataService->forgetCache($user->id);
        $this->logBlock($user, $candidate, $activeConnectionCount);
    }

    private function firstUnblockedConnection(User $user, Collection $activeConnections): ?ActiveConnection
    {
        $alreadyBlockedKeys = BlockedConfig::query()
            ->where('user_id', $user->id)
            ->get()
            ->map(fn (BlockedConfig $blocked) => $blocked->config_type.':'.$blocked->config_id)
            ->all();

        return $activeConnections->first(fn (ActiveConnection $connection) => ! in_array(
            $connection->config_type.':'.$connection->config_id,
            $alreadyBlockedKeys,
            true,
        ));
    }

    private function persistBlock(User $user, ActiveConnection $candidate, int $activeConnectionCount): void
    {
        DB::transaction(function () use ($user, $candidate, $activeConnectionCount): void {
            $blocked = BlockedConfig::query()->firstOrCreate(
                ['config_type' => $candidate->config_type, 'config_id' => $candidate->config_id],
                [
                    'user_id' => $user->id,
                    'server_id' => $candidate->server_id,
                    'reason' => $this->blockReason($user, $candidate, $activeConnectionCount, false),
                    'blocked_until' => now()->addMinutes(5),
                ],
            );

            if (! $blocked->wasRecentlyCreated) {
                $blocked->forceFill([
                    'reason' => $this->blockReason($user, $candidate, $activeConnectionCount, true),
                    'blocked_until' => now()->addMinutes(5),
                ])->save();
            }
        });
    }

    private function blockReason(
        User $user,
        ActiveConnection $candidate,
        int $activeConnectionCount,
        bool $existing,
    ): string {
        return sprintf(
            $existing
                ? 'Device limit still exceeded: %d active connections, limit %d, latest connection %s'
                : 'Device limit exceeded: %d active connections, limit %d, latest connection %s',
            $activeConnectionCount,
            (int) $user->max_devices,
            $candidate->ip,
        );
    }

    private function logBlock(User $user, ActiveConnection $candidate, int $activeConnectionCount): void
    {
        Log::info('Blocked config due to max devices limit.', [
            'user_id' => $user->id,
            'config_type' => $candidate->config_type,
            'config_id' => $candidate->config_id,
            'server_id' => $candidate->server_id,
            'ip' => $candidate->ip,
            'active_devices' => $activeConnectionCount,
            'max_devices' => (int) $user->max_devices,
        ]);
    }

    public function releaseExpiredBlocks(): void
    {
        $blockedConfigs = BlockedConfig::query()
            ->where('blocked_until', '<=', now())
            ->orderBy('blocked_until')
            ->get();

        foreach ($blockedConfigs as $blockedConfig) {
            $this->releaseIfPossible($blockedConfig);
        }
    }

    private function releaseIfPossible(BlockedConfig $blockedConfig): void
    {
        $lock = Cache::lock('device-limit:user:'.$blockedConfig->user_id, 60);

        $lock->block(5, function () use ($blockedConfig): void {
            $user = User::query()->find($blockedConfig->user_id);

            if (! $user) {
                $blockedConfig->delete();

                return;
            }

            $activeDeviceCount = ActiveConnection::query()
                ->where('user_id', $user->id)
                ->active()
                ->count();

            if ((int) $user->max_devices > 0 && $activeDeviceCount > (int) $user->max_devices) {
                $blockedConfig->forceFill([
                    'blocked_until' => now()->addMinutes(1),
                ])->save();

                return;
            }

            $this->clientAccess->enable($blockedConfig->config_type, (int) $blockedConfig->config_id);
            $blockedConfig->delete();
            $this->metadataService->forgetCache($user->id);

            Log::info('Unblocked config after active devices returned within limit.', [
                'user_id' => $user->id,
                'config_type' => $blockedConfig->config_type,
                'config_id' => $blockedConfig->config_id,
                'server_id' => $blockedConfig->server_id,
                'active_devices' => $activeDeviceCount,
                'max_devices' => (int) $user->max_devices,
            ]);
        });
    }
}
