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

    /**
     * @param  array<string, string>  $urls
     * @return array<string, array<string, string>>
     */
    public function prepare(array $urls, string $lastUpdated, ?XrayJsonSetting $previousSetting = null): array
    {
        $prepared = [];

        foreach ($urls as $asset => $url) {
            $url = trim($url);

            if ($url === '') {
                continue;
            }

            $this->assertHttpsUrl($url, $asset);

            $previousAsset = $this->previousAsset($previousSetting, $asset);
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
        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('Routing '.$asset.'.dat URL must use HTTPS.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function previousAsset(?XrayJsonSetting $previousSetting, string $asset): array
    {
        $item = data_get($previousSetting?->geodata, 'assets.'.$asset);

        return is_array($item) ? $item : [];
    }

    /**
     * @param  array<string, string>  $previousAsset
     */
    private function isCached(string $path, string $url, string $lastUpdated, array $previousAsset): bool
    {
        return is_file($path)
            && ($previousAsset['url'] ?? null) === $url
            && ($previousAsset['last_updated'] ?? '') === $lastUpdated
            && ($previousAsset['sha256'] ?? '') !== '';
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
    }
}
