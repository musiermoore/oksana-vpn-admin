<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\XrayInboundHealthCheck;
use App\Services\Subscriptions\XrayGeodataAssetService;
use App\Services\Subscriptions\XrayJsonProfileNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class XrayTunnelProbeRunner
{
    public function __construct(
        private readonly XrayJsonProfileNormalizer $profileNormalizer,
        private readonly XrayGeodataAssetService $geodataAssets,
    ) {}

    /** @var array<string, string> */
    private const TARGETS = [
        'Discord' => 'https://discord.com/',
        'Telegram' => 'https://telegram.org/',
        'Google' => 'https://www.google.com/generate_204',
        'ChatGPT' => 'https://chatgpt.com/',
        'YouTube' => 'https://www.youtube.com/',
    ];

    /** @param array<string, mixed> $profile @return array{status: string, results: array<string, mixed>, error: ?string} */
    public function runProfile(array $profile, int $targetTimeoutSeconds = 12): array
    {
        $proxyPort = $this->allocateLocalPort();
        $profile['inbounds'] = [[
            'listen' => '127.0.0.1',
            'port' => $proxyPort,
            'protocol' => 'socks',
            'settings' => ['auth' => 'noauth', 'udp' => false],
            'tag' => 'inbound-health-socks',
        ]];
        $preparedAssets = $this->geodataAssets->ensureProfileAssets($profile);
        $profile = $this->profileNormalizer->normalizeProfile($profile);
        $configPath = $this->writeConfig($profile, $proxyPort);

        try {
            return $this->probeTargets(
                $configPath,
                $proxyPort,
                $targetTimeoutSeconds,
                $this->geodataAssets->hasVerifiedAssetsForProfile($profile, $preparedAssets),
            );
        } finally {
            @unlink($configPath);
        }
    }

    /** @param array<string, mixed> $profile */
    private function writeConfig(array $profile, int $proxyPort): string
    {
        $profile['inbounds'][0]['port'] = $proxyPort;
        $temporaryPath = tempnam(sys_get_temp_dir(), 'inbound-health-xray-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to allocate a temporary Xray config file.');
        }

        $path = $temporaryPath.'.json';

        if (! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Unable to name the temporary Xray config as JSON.');
        }

        if (file_put_contents($path, json_encode($profile, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)) === false) {
            @unlink($path);
            throw new RuntimeException('Unable to write the temporary Xray config file.');
        }

        return $path;
    }

    /** @return array{status: string, results: array<string, mixed>, error: ?string} */
    private function probeTargets(
        string $configPath,
        int $proxyPort,
        int $targetTimeoutSeconds,
        bool $useManagedAssetDirectory,
    ): array
    {
        $binary = $this->binary();
        $validation = new Process([$binary, 'run', '-test', '-format=json', '-c', $configPath]);
        $this->configureAssetDirectory($validation, $useManagedAssetDirectory);
        $validation->setTimeout(15);
        $validation->run();

        if (! $validation->isSuccessful()) {
            throw new RuntimeException($this->validationError($validation));
        }

        $process = new Process([$binary, 'run', '-format=json', '-c', $configPath]);
        $this->configureAssetDirectory($process, $useManagedAssetDirectory);
        $process->setTimeout(90);
        $process->start();

        try {
            $this->waitForProxy($process, $proxyPort);
            $results = [];

            foreach (self::TARGETS as $name => $url) {
                $results[$name] = $this->probeTarget($url, $proxyPort, $targetTimeoutSeconds);
            }

            $failed = collect($results)->filter(fn (array $result): bool => ! $result['ok'])->keys()->all();
            $reached = count($results) - count($failed);
            $status = match (true) {
                $failed === [] => XrayInboundHealthCheck::STATUS_HEALTHY,
                $reached > 0 => XrayInboundHealthCheck::STATUS_WARNING,
                default => XrayInboundHealthCheck::STATUS_UNHEALTHY,
            };

            return [
                'status' => $status,
                'results' => $results,
                'error' => $failed === [] ? null : ($reached > 0 ? 'Partially reachable; failed destinations: ' : 'Failed destinations: ').implode(', ', $failed),
            ];
        } finally {
            $process->stop(1);
        }
    }

    private function waitForProxy(Process $process, int $proxyPort): void
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            if (! $process->isRunning()) {
                throw new RuntimeException('Xray exited before opening the local proxy: '.Str::limit(trim($process->getErrorOutput()), 500));
            }

            $socket = @fsockopen('127.0.0.1', $proxyPort, $errorCode, $errorMessage, 0.2);

            if (is_resource($socket)) {
                fclose($socket);

                return;
            }

            usleep(100_000);
        }

        throw new RuntimeException('Xray did not open its local SOCKS proxy.');
    }

    /** @return array<string, mixed> */
    private function probeTarget(string $url, int $proxyPort, int $timeoutSeconds): array
    {
        try {
            $response = Http::timeout($timeoutSeconds)
                ->withOptions(['proxy' => 'socks5h://127.0.0.1:'.$proxyPort])
                ->get($url);

            return ['ok' => $response->status() < 500, 'status' => $response->status()];
        } catch (Throwable $exception) {
            return ['ok' => false, 'error' => Str::limit($exception->getMessage(), 180)];
        }
    }

    private function allocateLocalPort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if ($socket === false) {
            throw new RuntimeException('Unable to allocate a local SOCKS port: '.$errorMessage);
        }

        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr((string) $address, strrpos((string) $address, ':') + 1);
    }

    private function validationError(Process $validation): string
    {
        $output = trim($validation->getErrorOutput().$validation->getOutput());
        $failure = Str::after($output, 'Failed to start: ');
        $failure = $failure !== $output ? $failure : $output;
        $failure = preg_replace('/\[\/tmp\/inbound-health-xray-[^]]+\]/', '[temporary config]', $failure) ?? $failure;
        $failure = preg_replace('/\s+/', ' ', $failure) ?? $failure;
        $lowerFailure = mb_strtolower($failure);
        $isGeodataFailure = str_contains($lowerFailure, 'geodata')
            || str_contains($lowerFailure, 'geoip')
            || str_contains($lowerFailure, 'geosite');

        if (mb_strlen($failure) > 220) {
            $failure = mb_substr($failure, 0, 90).' … '.mb_substr($failure, -110);
        }

        $message = 'Xray rejected the generated profile: '.$failure;

        if ($isGeodataFailure) {
            $message .= ' Check the generated routing rules and required geoip.dat/geosite.dat assets.';
        }

        return $message;
    }

    private function configureAssetDirectory(Process $process, bool $useManagedAssetDirectory): void
    {
        if ($useManagedAssetDirectory) {
            $process->setEnv(['XRAY_LOCATION_ASSET' => storage_path('app/xray-geodata')]);
        }
    }

    private function binary(): string
    {
        return trim((string) config('services.inbound_health.xray_binary', 'xray'));
    }
}
