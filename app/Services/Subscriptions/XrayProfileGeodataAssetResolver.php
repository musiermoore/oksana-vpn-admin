<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\XrayJsonSetting;
use App\Models\XrayRoutingGeodata;
use Illuminate\Support\Facades\File;

final class XrayProfileGeodataAssetResolver
{
    private const ASSET_DIR = 'xray-geodata';

    public function __construct(private readonly XrayGeodataAssetService $assets) {}

    /** @param array<string, mixed> $profile @return array<string, array<string, string>> */
    public function prepareProfile(array $profile): array
    {
        $profileAssets = data_get($profile, 'geodata.assets');

        if (! is_array($profileAssets)) {
            return [];
        }

        $globalSetting = XrayJsonSetting::query()->latestActive()->first();
        $globalAssets = data_get($globalSetting?->geodata, 'assets', []);
        $customSettings = XrayRoutingGeodata::query()->get();
        $preparedAssets = [];

        foreach ($profileAssets as $profileAsset) {
            if (! is_array($profileAsset)
                || ! is_string($profileAsset['url'] ?? null)
                || ! is_string($profileAsset['file'] ?? null)) {
                continue;
            }

            $assetName = $this->assetName($profileAsset['file']);

            if ($assetName === null) {
                continue;
            }

            $globalAsset = is_array($globalAssets) ? ($globalAssets[$assetName] ?? null) : null;

            if (is_array($globalAsset) && ($globalAsset['url'] ?? null) === $profileAsset['url']) {
                $preparedAssets[$assetName] = $this->prepareAsset(
                    $assetName,
                    $profileAsset['url'],
                    (string) data_get($globalSetting?->geodata, 'last_updated', ''),
                    $globalAsset,
                );

                continue;
            }

            $matchedCustomSetting = false;

            foreach ($customSettings as $customSetting) {
                $customAsset = $this->customAssets($customSetting)[$assetName] ?? null;

                if (! is_array($customAsset) || ($customAsset['url'] ?? null) !== $profileAsset['url']) {
                    continue;
                }

                $lastUpdated = (string) ($customSetting->last_updated
                    ?? $customSetting->updated_at?->toIso8601String()
                    ?? '');
                $preparedAsset = $this->prepareAsset(
                    $assetName,
                    $profileAsset['url'],
                    $lastUpdated,
                    $customAsset,
                );
                $preparedAssets[$assetName] = $preparedAsset;
                $this->storeCustomAsset($customSetting, $assetName, $preparedAsset);
                $matchedCustomSetting = true;

                break;
            }

            if ($matchedCustomSetting) {
                continue;
            }

            $previousAsset = $this->manifestAsset($assetName, $profileAsset['url']);
            $preparedAssets[$assetName] = $this->prepareAsset(
                $assetName,
                $profileAsset['url'],
                (string) ($profileAsset['last_updated'] ?? $previousAsset['last_updated'] ?? ''),
                $previousAsset,
            );
            $this->storeManifestAsset($assetName, $preparedAssets[$assetName]);
        }

        return $preparedAssets;
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, array<string, string>> $preparedAssets
     */
    public function hasVerifiedAssets(array $profile, array $preparedAssets): bool
    {
        $requiredAssets = $this->requiredAssets($profile);

        if ($requiredAssets === []) {
            return false;
        }

        $globalSetting = XrayJsonSetting::query()->latestActive()->first();

        foreach ($requiredAssets as $assetName) {
            $metadata = $preparedAssets[$assetName]
                ?? data_get($globalSetting?->geodata, 'assets.'.$assetName);

            if (! is_array($metadata) || ! is_string($metadata['url'] ?? null)) {
                return false;
            }

            $path = storage_path('app/'.self::ASSET_DIR.'/'.$assetName.'.dat');
            $lastUpdated = (string) ($metadata['last_updated']
                ?? data_get($globalSetting?->geodata, 'last_updated', ''));

            if (! $this->assets->isVerifiedAsset($path, $metadata['url'], $lastUpdated, $metadata)) {
                return false;
            }
        }

        return true;
    }

    private function prepareAsset(string $assetName, string $url, string $lastUpdated, array $previous): array
    {
        return $this->assets->prepare(
            [$assetName => $url],
            $lastUpdated,
            [$assetName => $previous],
        )[$assetName] ?? [];
    }

    /** @return array<int, string> */
    private function requiredAssets(array $profile): array
    {
        $required = [];
        $rules = data_get($profile, 'routing.rules', []);

        foreach (is_array($rules) ? $rules : [] as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            foreach (['ip' => 'geoip', 'domain' => 'geosite'] as $field => $assetName) {
                $values = $rule[$field] ?? [];

                foreach (is_array($values) ? $values : [] as $value) {
                    if (is_string($value) && str_starts_with($value, $assetName.':')) {
                        $required[$assetName] = $assetName;
                        break;
                    }
                }
            }
        }

        return array_values($required);
    }

    private function assetName(string $file): ?string
    {
        return match (basename($file)) {
            'geoip.dat' => 'geoip',
            'geosite.dat' => 'geosite',
            default => null,
        };
    }

    /** @return array<string, array<string, string>> */
    private function customAssets(XrayRoutingGeodata $setting): array
    {
        $assets = [];

        foreach (is_array($setting->assets) ? $setting->assets : [] as $asset) {
            if (! is_array($asset) || ! is_string($asset['url'] ?? null) || ! is_string($asset['file'] ?? null)) {
                continue;
            }

            $assetName = $this->assetName($asset['file']);

            if ($assetName !== null) {
                $assets[$assetName] = $asset;
            }
        }

        foreach (['geoip' => $setting->geoip_url, 'geosite' => $setting->geosite_url] as $assetName => $url) {
            if (is_string($url) && $url !== '' && ! isset($assets[$assetName])) {
                $assets[$assetName] = ['url' => $url, 'file' => $assetName.'.dat'];
            }
        }

        return $assets;
    }

    /** @param array<string, string> $preparedAsset */
    private function storeCustomAsset(XrayRoutingGeodata $setting, string $assetName, array $preparedAsset): void
    {
        $assets = collect(is_array($setting->assets) ? $setting->assets : [])
            ->reject(fn (mixed $asset): bool => is_array($asset)
                && $this->assetName((string) ($asset['file'] ?? '')) === $assetName)
            ->values();
        $assets->push(collect($preparedAsset)->only(['url', 'file', 'sha256', 'last_updated'])->all());
        $setting->forceFill([
            'assets' => $assets->all(),
            'last_updated' => $setting->last_updated ?? $preparedAsset['last_updated'] ?? null,
        ])->save();
    }

    /** @return array<string, string> */
    private function manifestAsset(string $assetName, string $url): array
    {
        $asset = $this->readManifest()[$assetName] ?? null;

        return is_array($asset) && ($asset['url'] ?? null) === $url ? $asset : [];
    }

    /** @param array<string, string> $preparedAsset */
    private function storeManifestAsset(string $assetName, array $preparedAsset): void
    {
        $manifest = $this->readManifest();
        $manifest[$assetName] = collect($preparedAsset)->only(['url', 'file', 'sha256', 'last_updated'])->all();
        $path = storage_path('app/'.self::ASSET_DIR.'/manifest.json');
        File::ensureDirectoryExists(dirname($path));
        $temporaryPath = $path.'.tmp.'.bin2hex(random_bytes(8));

        file_put_contents($temporaryPath, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), LOCK_EX);
        rename($temporaryPath, $path);
    }

    /** @return array<string, array<string, string>> */
    private function readManifest(): array
    {
        $path = storage_path('app/'.self::ASSET_DIR.'/manifest.json');

        if (! is_file($path)) {
            return [];
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        return is_array($manifest) ? $manifest : [];
    }
}
