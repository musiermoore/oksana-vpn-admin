<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActiveConnection;
use App\Models\User;
use App\Models\UserServerStatHistory;
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
    public function buildHeaders(
        User $user,
        string $fileExtension = 'txt',
        ?string $contentType = null,
        ?string $profileTitle = null,
        bool $includeDeviceHeaders = true,
        bool $includeDownloadFilename = true,
        bool $includeTrafficUsage = true,
        string $profileUpdateInterval = '24',
    ): array
    {
        $payload = $this->payload($user);
        $resolvedProfileTitle = $this->resolveProfileTitle($payload['filename'], $profileTitle);

        $headers = [
            'Profile-Update-Interval' => $profileUpdateInterval,
            'Profile-Title' => $resolvedProfileTitle,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
        ];

        if ($includeTrafficUsage) {
            $headers['Subscription-Userinfo'] = sprintf(
                'upload=%d; download=%d; total=%d; expire=%d',
                $payload['upload'],
                $payload['download'],
                $payload['total'],
                $payload['expire'],
            );
        }

        if ($includeDownloadFilename) {
            $headers['Content-Disposition'] = 'attachment; filename="'.$this->replaceExtension($resolvedProfileTitle, $fileExtension).'"';
        }

        if ($includeDeviceHeaders) {
            $headers['X-Subscription-Devices-Limit'] = (string) $payload['max_devices'];
            $headers['X-Subscription-Devices-Used'] = (string) $payload['active_devices'];
        }

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
        $traffic = $this->monthlyTrafficTotals($user);

        $activeDevices = ActiveConnection::query()
            ->where('user_id', $user->id)
            ->active()
            ->count();

        return [
            'upload' => $traffic['upload'],
            'download' => $traffic['download'],
            'total' => (int) ($user->traffic_limit_bytes ?? 0),
            'expire' => (int) optional($user->subscription_expires_at)->timestamp,
            'active_devices' => $activeDevices,
            'max_devices' => (int) ($user->max_devices ?? 0),
            'filename' => $this->buildFilename($user),
        ];
    }

    /** @return array{upload:int,download:int} */
    private function monthlyTrafficTotals(User $user): array
    {
        $periodStart = now()->startOfMonth();
        $baselineRows = UserServerStatHistory::query()
            ->where('user_id', $user->id)
            ->where('collected_at', '<', $periodStart)
            ->whereRaw(
                'collected_at = (select max(previous.collected_at) from user_server_stat_histories as previous where previous.user_id = user_server_stat_histories.user_id and previous.server_id = user_server_stat_histories.server_id and previous.collected_at < ?)',
                [$periodStart],
            )
            ->get()
            ->keyBy('server_id');

        $previousByServer = $baselineRows->map(fn (UserServerStatHistory $row): array => [
            'upload' => (int) $row->upload_bytes,
            'download' => (int) $row->download_bytes,
        ])->all();

        $totals = ['upload' => 0, 'download' => 0];
        $monthlyRows = UserServerStatHistory::query()
            ->where('user_id', $user->id)
            ->where('collected_at', '>=', $periodStart)
            ->orderBy('server_id')
            ->orderBy('collected_at')
            ->orderBy('id')
            ->get();

        foreach ($monthlyRows as $row) {
            $serverId = (int) $row->server_id;
            $current = [
                'upload' => (int) $row->upload_bytes,
                'download' => (int) $row->download_bytes,
            ];
            $previous = $previousByServer[$serverId] ?? null;

            if ($previous !== null) {
                $totals['upload'] += $this->trafficDelta($previous['upload'], $current['upload']);
                $totals['download'] += $this->trafficDelta($previous['download'], $current['download']);
            }

            $previousByServer[$serverId] = $current;
        }

        return $totals;
    }

    private function trafficDelta(int $previous, int $current): int
    {
        return $current >= $previous ? $current - $previous : $current;
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

    private function resolveProfileTitle(string $defaultFilename, ?string $profileTitle): string
    {
        $title = trim((string) $profileTitle);

        if ($title !== '') {
            return $title;
        }

        return pathinfo($defaultFilename, PATHINFO_FILENAME) ?: 'subscription';
    }
}
