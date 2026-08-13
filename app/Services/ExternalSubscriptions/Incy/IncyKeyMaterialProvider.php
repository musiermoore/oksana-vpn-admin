<?php

declare(strict_types=1);

namespace App\Services\ExternalSubscriptions\Incy;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IncyKeyMaterialProvider
{
    private const CACHE_KEY = 'external_subscriptions:incy:key_material';

    /**
     * @return array{km_a:string, km_b:string}
     */
    public function get(): array
    {
        $ttlSeconds = max(300, (int) config('incy.keymat.cache_ttl_seconds', 86400));

        return Cache::remember(
            self::CACHE_KEY,
            now()->addSeconds($ttlSeconds),
            fn (): array => $this->fetchRemoteOrFallbackToLocal()
        );
    }

    /**
     * @return array{km_a:string, km_b:string}
     */
    private function fetchRemoteOrFallbackToLocal(): array
    {
        if ((bool) config('incy.keymat.remote_enabled', true)) {
            try {
                return $this->fetchRemote();
            } catch (\Throwable) {
                // Fall back to the bundled local key material when GitHub is unavailable.
            }
        }

        return $this->getLocal();
    }

    /**
     * @return array{km_a:string, km_b:string}
     */
    private function fetchRemote(): array
    {
        $remoteUrl = trim((string) config('incy.keymat.remote_url', ''));

        if ($remoteUrl === '') {
            throw new RuntimeException('INCY remote keymat URL is not configured.');
        }

        $response = Http::timeout(max(5, (int) config('incy.keymat.request_timeout_seconds', 10)))
            ->get($remoteUrl);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to fetch INCY key material from remote source.');
        }

        return $this->parseKeymatSource((string) $response->body());
    }

    /**
     * @return array{km_a:string, km_b:string}
     */
    private function getLocal(): array
    {
        $kmA = base64_decode((string) config('incy.keymat.local_km_a_b64', ''), true);
        $kmB = base64_decode((string) config('incy.keymat.local_km_b_b64', ''), true);

        if ($kmA === false || $kmB === false || strlen($kmA) !== 32 || strlen($kmB) !== 32) {
            throw new RuntimeException('Bundled INCY key material is invalid.');
        }

        return [
            'km_a' => $kmA,
            'km_b' => $kmB,
        ];
    }

    /**
     * @return array{km_a:string, km_b:string}
     */
    private function parseKeymatSource(string $source): array
    {
        if (! preg_match("/KEYMAT_A_B64 = '([^']+)'/", $source, $aMatches)) {
            throw new RuntimeException('Unable to parse KEYMAT_A_B64 from INCY keymat source.');
        }

        if (! preg_match("/KEYMAT_B_B64 = '([^']+)'/", $source, $bMatches)) {
            throw new RuntimeException('Unable to parse KEYMAT_B_B64 from INCY keymat source.');
        }

        $a = base64_decode($aMatches[1], true);
        $b = base64_decode($bMatches[1], true);

        $aOffset = (int) config('incy.keymat.km_a_offset', 1024);
        $bOffset = (int) config('incy.keymat.km_b_offset', 2048);
        $length = (int) config('incy.keymat.key_length', 32);

        if (
            $a === false
            || $b === false
            || strlen($a) < $aOffset + $length
            || strlen($b) < $bOffset + $length
        ) {
            throw new RuntimeException('Remote INCY key material is too short.');
        }

        return [
            'km_a' => substr($a, $aOffset, $length),
            'km_b' => substr($b, $bOffset, $length),
        ];
    }
}
