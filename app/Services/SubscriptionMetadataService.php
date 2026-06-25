<?php

namespace App\Services;

use App\Models\ActiveConnection;
use App\Models\User;
use App\Models\UserServerStat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SubscriptionMetadataService
{
    private const CACHE_TTL_SECONDS = 60;

    public function getUpload(User $user): int
    {
        return (int) $this->payload($user)['upload'];
    }

    public function getDownload(User $user): int
    {
        return (int) $this->payload($user)['download'];
    }

    public function getTotalTraffic(User $user): int
    {
        return (int) $this->payload($user)['total'];
    }

    public function getExpireTimestamp(User $user): int
    {
        return (int) $this->payload($user)['expire'];
    }

    public function getActiveDevices(User $user): int
    {
        return (int) $this->payload($user)['active_devices'];
    }

    public function getMaxDevices(User $user): int
    {
        return (int) $this->payload($user)['max_devices'];
    }

    /**
     * @return array<string, string>
     */
    public function buildHeaders(User $user, string $fileExtension = 'txt', ?string $contentType = null): array
    {
        $payload = $this->payload($user);

        $headers = [
            'Subscription-Userinfo' => sprintf(
                'upload=%d; download=%d; total=%d; expire=%d',
                $payload['upload'],
                $payload['download'],
                $payload['total'],
                $payload['expire'],
            ),
            'Profile-Update-Interval' => '24',
            'X-Subscription-Devices-Limit' => (string) $payload['max_devices'],
            'X-Subscription-Devices-Used' => (string) $payload['active_devices'],
            'Content-Disposition' => 'attachment; filename="'.$this->replaceExtension($payload['filename'], $fileExtension).'"',
        ];

        if ($contentType !== null && $contentType !== '') {
            $headers['Content-Type'] = $contentType;
        }

        return $headers;
    }

    public function forgetCache(int $userId): void
    {
        Cache::forget($this->cacheKey($userId));
    }

    /**
     * @return array{upload:int, download:int, total:int, expire:int, active_devices:int, max_devices:int, filename:string}
     */
    private function payload(User $user): array
    {
        return Cache::remember(
            $this->cacheKey((int) $user->id),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            fn () => $this->buildPayload($user->fresh(['latestActiveOrFutureSubscription']) ?? $user),
        );
    }

    /**
     * @return array{upload:int, download:int, total:int, expire:int, active_devices:int, max_devices:int, filename:string}
     */
    private function buildPayload(User $user): array
    {
        $aggregate = UserServerStat::query()
            ->where('user_id', $user->id)
            ->selectRaw('COALESCE(SUM(upload_bytes), 0) as upload_total, COALESCE(SUM(download_bytes), 0) as download_total')
            ->first();

        $activeDevices = ActiveConnection::query()
            ->where('user_id', $user->id)
            ->active()
            ->distinct('ip')
            ->count('ip');

        return [
            'upload' => (int) ($aggregate->upload_total ?? 0),
            'download' => (int) ($aggregate->download_total ?? 0),
            'total' => (int) ($user->traffic_limit_bytes ?? 0),
            'expire' => (int) optional($user->subscription_expires_at)->timestamp,
            'active_devices' => $activeDevices,
            'max_devices' => (int) ($user->max_devices ?? 0),
            'filename' => $this->buildFilename($user),
        ];
    }

    private function buildFilename(User $user): string
    {
        $base = trim((string) ($user->name ?: $user->telegram ?: 'subscription'));
        $normalized = Str::of($base)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9._ -]+/', '')
            ->trim()
            ->limit(80, '')
            ->value();

        return ($normalized !== '' ? $normalized : 'subscription').'.txt';
    }

    private function cacheKey(int $userId): string
    {
        return 'subscription-metadata:'.$userId;
    }

    private function replaceExtension(string $filename, string $extension): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $safeExtension = trim($extension, '.');

        return ($name !== '' ? $name : 'subscription').'.'.$safeExtension;
    }
}
