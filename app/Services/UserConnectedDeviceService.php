<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\UserConnectedDeviceRepository;
use Illuminate\Http\Request;
use Throwable;

class UserConnectedDeviceService
{
    public function __construct(
        private readonly UserConnectedDeviceRepository $devices,
    ) {}

    public function recordConnection(User $user, Request $request): void
    {
        if ($request->boolean('skip_connection')) {
            return;
        }

        try {
            $userAgent = $this->normalizeUserAgent($request->userAgent());
            $now = now();
            $device = $this->resolveDevice($userAgent);
            $userAgentHash = hash('sha256', $userAgent ?? '');
            $routeName = $request->route()?->getName();

            $connectedDevice = $this->devices->findActiveByUserAndAgentHash((int) $user->id, $userAgentHash);

            if (! $connectedDevice) {
                $this->devices->create([
                    'user_id' => $user->id,
                    'device' => $device,
                    'user_agent' => $userAgent,
                    'user_agent_hash' => $userAgentHash,
                    'ip_address' => $request->ip(),
                    'first_connection_at' => $now,
                    'last_connection_at' => $now,
                    'connection_count' => 1,
                    'last_connection_route' => $routeName,
                ]);

                return;
            }

            $connectedDevice->forceFill([
                'device' => $device,
                'user_agent' => $userAgent,
                'ip_address' => $request->ip(),
                'last_connection_at' => $now,
                'connection_count' => (int) $connectedDevice->connection_count + 1,
                'last_connection_route' => $routeName,
            ])->save();
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }

    private function normalizeUserAgent(?string $userAgent): ?string
    {
        $userAgent = trim((string) $userAgent);

        return $userAgent === '' ? null : mb_substr($userAgent, 0, 2000);
    }

    private function resolveDevice(?string $userAgent): string
    {
        if ($userAgent === null) {
            return 'Unknown device';
        }

        $client = $this->resolveClient($userAgent);
        $platform = $this->resolvePlatform($userAgent);

        if ($client === null && $platform === null) {
            return mb_substr($userAgent, 0, 255);
        }

        return trim(implode(' on ', array_filter([$client, $platform])));
    }

    private function resolveClient(string $userAgent): ?string
    {
        $lowerUserAgent = mb_strtolower($userAgent);

        return match (true) {
            str_contains($lowerUserAgent, 'telegram') => 'Telegram',
            str_contains($lowerUserAgent, 'happ') => 'Happ',
            str_contains($lowerUserAgent, 'hiddify') => 'Hiddify',
            str_contains($lowerUserAgent, 'v2raytun') => 'V2RayTun',
            str_contains($lowerUserAgent, 'v2rayng') => 'V2RayNG',
            str_contains($lowerUserAgent, 'v2rayn') => 'V2RayN',
            str_contains($lowerUserAgent, 'v2box') => 'V2Box',
            str_contains($lowerUserAgent, 'sing-box') || str_contains($lowerUserAgent, 'singbox') => 'sing-box',
            str_contains($lowerUserAgent, 'clash') => 'Clash',
            str_contains($lowerUserAgent, 'chrome') => 'Chrome',
            str_contains($lowerUserAgent, 'safari') => 'Safari',
            str_contains($lowerUserAgent, 'firefox') => 'Firefox',
            default => null,
        };
    }

    private function resolvePlatform(string $userAgent): ?string
    {
        $lowerUserAgent = mb_strtolower($userAgent);

        return match (true) {
            str_contains($lowerUserAgent, 'iphone') => 'iPhone',
            str_contains($lowerUserAgent, 'ipad') => 'iPad',
            str_contains($lowerUserAgent, 'android') => 'Android',
            str_contains($lowerUserAgent, 'windows') => 'Windows',
            str_contains($lowerUserAgent, 'mac os x') || str_contains($lowerUserAgent, 'macintosh') => 'macOS',
            str_contains($lowerUserAgent, 'linux') => 'Linux',
            default => null,
        };
    }
}
