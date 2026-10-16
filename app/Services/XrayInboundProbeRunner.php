<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Subscription\NormalizedNode;
use App\Models\Server;
use App\Models\VlessConfig;
use App\Models\XrayInbound;
use App\Models\XrayInboundHealthCheck;
use App\Services\Subscriptions\Builders\ConnectJsonBuilder;
use App\Services\Subscriptions\SubscriptionBuilderFactory;
use App\Services\Subscriptions\SubscriptionUriParser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class XrayInboundProbeRunner
{
    /** @var array<string, string> */
    private const TARGETS = [
        'Discord' => 'https://discord.com/',
        'Telegram' => 'https://telegram.org/',
        'Google' => 'https://www.google.com/generate_204',
        'ChatGPT' => 'https://chatgpt.com/',
        'YouTube' => 'https://www.youtube.com/',
    ];

    public function __construct(
        private readonly SubscriptionUriParser $uriParser,
        private readonly SubscriptionBuilderFactory $builderFactory,
        private readonly ConnectJsonBuilder $connectJsonBuilder,
    ) {}

    public function isAvailable(): bool
    {
        $binary = $this->binary();

        if (str_contains($binary, DIRECTORY_SEPARATOR)) {
            return is_executable($binary);
        }

        $process = new Process(['sh', '-lc', 'command -v '.escapeshellarg($binary)]);
        $process->run();

        return $process->isSuccessful();
    }

    /** @return array{status: string, results: array<string, mixed>, error: ?string} */
    public function check(VlessConfig $config, Server $server, XrayInbound $inbound): array
    {
        $uri = $config->getStaticLink();
        $parsed = $this->uriParser->parse($uri);

        if (! is_array($parsed)) {
            throw new RuntimeException('The inbound protocol could not be converted to a client URI.');
        }

        $node = new NormalizedNode(
            id: 'health-'.$inbound->id,
            serverName: 'inbound-health-check',
            sortGroupOrder: 0,
            sortItemOrder: 0,
            protocol: (string) $parsed['protocol'],
            transport: $this->uriParser->detectTransport($uri),
            uri: $uri,
            serverId: (int) $server->id,
            configId: (int) $inbound->id,
            sourceType: 'health-check',
            sortServerName: 'inbound-health-check',
            meta: ['name' => 'inbound-health-check'],
        );

        $connectLink = $this->buildConnectLink($node);
        $connectV2Profile = $this->buildConnectV2Profile($node, $connectLink);

        $connectV2Profile['routing'] = [
            'domainStrategy' => 'AsIs',
            'rules' => [],
        ];

        return $this->runProfile($connectV2Profile);
    }

    /** @return array{status: string, results: array<string, mixed>, error: ?string} */
    public function checkSubscriptionNode(NormalizedNode $node, bool $applyRouting = true): array
    {
        if (! $applyRouting && array_key_exists('json_profile', $node->meta)) {
            $meta = $node->meta;
            unset($meta['json_profile']);
            $node = new NormalizedNode(
                id: $node->id,
                serverName: $node->serverName,
                sortGroupOrder: $node->sortGroupOrder,
                sortItemOrder: $node->sortItemOrder,
                protocol: $node->protocol,
                transport: $node->transport,
                uri: $node->uri,
                serverId: $node->serverId,
                configId: $node->configId,
                sourceType: $node->sourceType,
                sortServerName: $node->sortServerName,
                meta: $meta,
            );
        }

        $connectLink = $this->buildConnectLink($node);
        $profile = $this->buildConnectV2Profile($node, $connectLink);

        if (! $applyRouting) {
            $profile['routing'] = [
                'domainStrategy' => 'AsIs',
                'rules' => [],
            ];
        }

        return $this->runProfile($profile, 5);
    }

    /** @param array<string, mixed> $profile @return array{status: string, results: array<string, mixed>, error: ?string} */
    public function checkGeneratedProfile(array $profile): array
    {
        if (! is_array($profile['outbounds'] ?? null) || $profile['outbounds'] === []) {
            throw new RuntimeException('Generated subscription profile has no Xray outbounds.');
        }

        return $this->runProfile($profile, 5);
    }

    /** @param array<string, mixed> $profile @return array{status: string, results: array<string, mixed>, error: ?string} */
    private function runProfile(array $profile, int $targetTimeoutSeconds = 12): array
    {
        $proxyPort = $this->allocateLocalPort();
        $profile['inbounds'] = [[
            'listen' => '127.0.0.1',
            'port' => $proxyPort,
            'protocol' => 'socks',
            'settings' => ['auth' => 'noauth', 'udp' => false],
            'tag' => 'inbound-health-socks',
        ]];
        $configPath = $this->writeConfig($profile, $proxyPort);

        try {
            return $this->probeTargets($configPath, $proxyPort, $targetTimeoutSeconds);
        } finally {
            @unlink($configPath);
        }
    }

    private function buildConnectLink(NormalizedNode $node): string
    {
        $result = $this->builderFactory->make('uri')->build([$node]);
        $decoded = base64_decode($result->content, true);
        $uri = is_string($decoded) ? trim($decoded) : '';

        if ($uri === '' || str_contains($uri, "\n")) {
            throw new RuntimeException('The /connect subscription builder did not produce one client link.');
        }

        return $uri;
    }

    /** @return array<string, mixed> */
    private function buildConnectV2Profile(NormalizedNode $node, string $connectLink): array
    {
        $node = new NormalizedNode(
            id: $node->id,
            serverName: $node->serverName,
            sortGroupOrder: $node->sortGroupOrder,
            sortItemOrder: $node->sortItemOrder,
            protocol: $node->protocol,
            transport: $node->transport,
            uri: $connectLink,
            serverId: $node->serverId,
            configId: $node->configId,
            sourceType: $node->sourceType,
            sortServerName: $node->sortServerName,
            meta: $node->meta,
        );
        $result = $this->connectJsonBuilder->build([$node]);
        $profiles = json_decode($result->content, true, flags: JSON_THROW_ON_ERROR);
        $profile = is_array($profiles) ? ($profiles[0] ?? null) : null;

        if (! is_array($profile) || ! is_array($profile['outbounds'] ?? null) || ($profile['outbounds'][0] ?? null) === null) {
            throw new RuntimeException('Generated subscription profile did not contain an Xray outbound.');
        }

        return $profile;
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
    private function probeTargets(string $configPath, int $proxyPort, int $targetTimeoutSeconds): array
    {
        $binary = $this->binary();
        $validation = new Process([$binary, 'run', '-test', '-format=json', '-c', $configPath]);
        $validation->setTimeout(15);
        $validation->run();

        if (! $validation->isSuccessful()) {
            throw new RuntimeException('Xray rejected the generated profile: '.Str::limit(trim($validation->getErrorOutput().$validation->getOutput()), 500));
        }

        $process = new Process([$binary, 'run', '-format=json', '-c', $configPath]);
        $process->setTimeout(90);
        $process->start();

        try {
            $this->waitForProxy($process, $proxyPort);
            $results = [];

            foreach (self::TARGETS as $name => $url) {
                $results[$name] = $this->probeTarget($url, $proxyPort, $targetTimeoutSeconds);
            }

            $failed = collect($results)->filter(fn (array $result): bool => ! $result['ok'])->keys()->all();

            return [
                'status' => $failed === [] ? XrayInboundHealthCheck::STATUS_HEALTHY : XrayInboundHealthCheck::STATUS_UNHEALTHY,
                'results' => $results,
                'error' => $failed === [] ? null : 'Failed destinations: '.implode(', ', $failed),
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

    private function binary(): string
    {
        return trim((string) config('services.inbound_health.xray_binary', 'xray'));
    }
}
