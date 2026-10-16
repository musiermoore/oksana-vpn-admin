<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\XrayJsonSetting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class XrayGeodataAssetService
{
    private const ASSET_DIR = 'xray-geodata';

    /** @var array<string, bool> */
    private static array $verifiedAssetHashes = [];

    /**
     * @param  array<string, mixed>|null  $profileGeodata
     * @param  array<string, mixed>|null  $defaultGeodata
     * @return array<string, mixed>|null
     */
    public function mergeDefaultGeodata(?array $profileGeodata, ?array $defaultGeodata): ?array
    {
        if ($profileGeodata === null || $profileGeodata === []) {
            return $defaultGeodata;
        }

        if ($defaultGeodata === null) {
            return $profileGeodata;
        }

        $profileAssets = is_array($profileGeodata['assets'] ?? null) ? array_values($profileGeodata['assets']) : [];
        $existingFiles = collect($profileAssets)
            ->map(fn (mixed $asset): string => is_array($asset) ? (string) ($asset['file'] ?? '') : '')
            ->filter()
            ->all();
        $missingDefaultAssets = collect($defaultGeodata['assets'] ?? [])
            ->reject(fn (mixed $asset): bool => is_array($asset)
                && in_array((string) ($asset['file'] ?? ''), $existingFiles, true))
            ->values()
            ->all();

        return [
            ...$defaultGeodata,
            ...$profileGeodata,
            'assets' => [...$profileAssets, ...$missingDefaultAssets],
        ];
    }

    /**
     * @param  array<string, string>  $urls
     * @return array<string, array<string, string>>
     */
    public function prepare(array $urls, string $lastUpdated, array $previousAssets = []): array
    {
        $prepared = [];

        foreach ($urls as $asset => $url) {
            $url = trim($url);

            if ($url === '') {
                continue;
            }

            $this->assertHttpsUrl($url, $asset);

            $previousAsset = $previousAssets[$asset] ?? [];
            $path = storage_path('app/'.self::ASSET_DIR.'/'.$asset.'.dat');

            Log::info('Routing '.$asset.' URL: '.$url);
            Log::info('Xray asset cache path: '.$path);

            if ($this->isCached($path, $url, $lastUpdated, $previousAsset)) {
                Log::info('Using cached '.$asset.'.dat: '.$path);

                $prepared[$asset] = [
                    ...$previousAsset,
                    'url' => $url,
                    'file' => $asset.'.dat',
                    'path' => $path,
                    'last_updated' => $lastUpdated,
                ];

                continue;
            }

            Log::info('Downloading '.$asset.'.dat: '.$url);

            $content = $this->download($url, $asset);
            $hash = hash('sha256', $content);
            $this->replaceAtomically($path, $content);

            Log::info('Updated '.$asset.'.dat successfully', [
                'path' => $path,
                'sha256' => $hash,
                'last_updated' => $lastUpdated,
            ]);

            $prepared[$asset] = [
                'url' => $url,
                'file' => $asset.'.dat',
                'path' => $path,
                'sha256' => $hash,
                'last_updated' => $lastUpdated,
            ];
        }

        return $prepared;
    }

    private function assertHttpsUrl(string $url, string $asset): void
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! is_string($host) || $host === '') {
            throw new RuntimeException('Routing '.$asset.'.dat URL must use HTTPS.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new RuntimeException('Routing '.$asset.'.dat URL cannot target a private or reserved IP address.');
        }
    }

    /**
     * @param  array<string, string>  $previousAsset
     */
    private function isCached(string $path, string $url, string $lastUpdated, array $previousAsset): bool
    {
        if (! is_file($path)
            || ($previousAsset['url'] ?? null) !== $url
            || ($previousAsset['last_updated'] ?? '') !== $lastUpdated
            || ($previousAsset['sha256'] ?? '') === '') {
            return false;
        }

        $cacheKey = implode(':', [
            $path,
            (string) filesize($path),
            (string) filemtime($path),
            (string) $previousAsset['sha256'],
        ]);

        if (array_key_exists($cacheKey, self::$verifiedAssetHashes)) {
            return self::$verifiedAssetHashes[$cacheKey];
        }

        $actualHash = hash_file('sha256', $path);
        $isValid = is_string($actualHash) && hash_equals((string) $previousAsset['sha256'], $actualHash);
        self::$verifiedAssetHashes[$cacheKey] = $isValid;

        return $isValid;
    }

    /** @param array<string, string> $metadata */
    public function isVerifiedAsset(string $path, string $url, string $lastUpdated, array $metadata): bool
    {
        return $this->isCached($path, $url, $lastUpdated, $metadata);
    }

    private function download(string $url, string $asset): string
    {
        $response = Http::timeout(180)
            ->accept('*/*')
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to download routing '.$asset.'.dat from '.$url);
        }

        $body = (string) $response->body();

        if ($body === '') {
            throw new RuntimeException('Failed to download routing '.$asset.'.dat from '.$url.': empty response');
        }

        return $body;
    }

    private function replaceAtomically(string $path, string $content): void
    {
        File::ensureDirectoryExists(dirname($path));

        $tempPath = $path.'.tmp.'.bin2hex(random_bytes(8));

        file_put_contents($tempPath, $content, LOCK_EX);
        rename($tempPath, $path);

        foreach (array_keys(self::$verifiedAssetHashes) as $cacheKey) {
            if (str_starts_with($cacheKey, $path.':')) {
                unset(self::$verifiedAssetHashes[$cacheKey]);
            }
        }
    }

}
