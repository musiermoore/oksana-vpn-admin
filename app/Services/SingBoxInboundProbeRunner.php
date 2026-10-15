<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Subscription\NormalizedNode;
use App\Models\Server;
use App\Models\VlessConfig;
use App\Models\XrayInbound;
use App\Models\XrayInboundHealthCheck;
use App\Services\Subscriptions\Builders\SingBoxBuilder;
use App\Services\Subscriptions\SubscriptionUriParser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class SingBoxInboundProbeRunner
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
        private readonly SingBoxBuilder $singBoxBuilder,
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

        if ($uri === '' || $this->uriParser->parse($uri) === null) {
            throw new RuntimeException('The inbound protocol could not be converted to a probe client URI.');
        }

        $proxyPort = $this->allocateLocalPort();
        $configPath = $this->writeConfig($uri, $server, $inbound, $proxyPort);

        try {
            return $this->probeTargets($configPath, $proxyPort);
        } finally {
            @unlink($configPath);
        }
    }

    private function writeConfig(string $uri, Server $server, XrayInbound $inbound, int $proxyPort): string
    {
        $node = new NormalizedNode(
            id: 'health-'.$inbound->id,
            serverName: 'inbound-health-check',
            sortGroupOrder: 0,
            sortItemOrder: 0,
            protocol: $this->uriParser->detectProtocol($uri),
            transport: $this->uriParser->detectTransport($uri),
            uri: $uri,
            serverId: (int) $server->id,
            configId: (int) $inbound->id,
            sourceType: 'health-check',
            sortServerName: 'inbound-health-check',
            meta: ['name' => 'inbound-health-check'],
        );
        $profile = json_decode($this->singBoxBuilder->build([$node])->content, true, flags: JSON_THROW_ON_ERROR);
        $outbound = collect($profile['outbounds'] ?? [])
            ->first(fn (array $row): bool => ($row['tag'] ?? null) === 'inbound-health-check');

        if (! is_array($outbound)) {
            throw new RuntimeException('sing-box could not build an outbound for this inbound.');
        }

        $profile['outbounds'] = [$outbound, ['type' => 'direct', 'tag' => 'direct'], ['type' => 'block', 'tag' => 'block']];
        $profile['inbounds'] = [[
            'type' => 'socks',
            'tag' => 'health-probe-socks',
            'listen' => '127.0.0.1',
            'listen_port' => $proxyPort,
        ]];
        $profile['route'] = ['final' => 'inbound-health-check'];

        $path = tempnam(sys_get_temp_dir(), 'inbound-health-');

        if ($path === false) {
            throw new RuntimeException('Unable to allocate a temporary sing-box config file.');
        }

        file_put_contents($path, json_encode($profile, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /** @return array{status: string, results: array<string, mixed>, error: ?string} */
    private function probeTargets(string $configPath, int $proxyPort): array
    {
        $process = new Process([$this->binary(), 'run', '-c', $configPath]);
        $process->setTimeout(90);
        $process->start();

        try {
            $this->waitForProxy($process, $proxyPort);
            $results = [];

            foreach (self::TARGETS as $name => $url) {
                $results[$name] = $this->probeTarget($url, $proxyPort);
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
                throw new RuntimeException('sing-box exited before opening the local proxy: '.trim($process->getErrorOutput()));
            }

            $socket = @fsockopen('127.0.0.1', $proxyPort, $errorCode, $errorMessage, 0.2);

            if (is_resource($socket)) {
                fclose($socket);

                return;
            }

            usleep(100_000);
        }

        throw new RuntimeException('sing-box did not open its local SOCKS proxy.');
    }

    /** @return array<string, mixed> */
    private function probeTarget(string $url, int $proxyPort): array
    {
        try {
            $response = Http::timeout(12)
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
        return trim((string) config('services.inbound_health.sing_box_binary', 'sing-box'));
    }
}
