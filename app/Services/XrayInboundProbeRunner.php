<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Subscription\NormalizedNode;
use App\Models\Server;
use App\Models\VlessConfig;
use App\Models\XrayInbound;
use App\Services\Subscriptions\Builders\ConnectJsonBuilder;
use App\Services\Subscriptions\SubscriptionBuilderFactory;
use App\Services\Subscriptions\SubscriptionUriParser;
use RuntimeException;
use Symfony\Component\Process\Process;

final class XrayInboundProbeRunner
{
    public function __construct(
        private readonly SubscriptionUriParser $uriParser,
        private readonly SubscriptionBuilderFactory $builderFactory,
        private readonly ConnectJsonBuilder $connectJsonBuilder,
        private readonly XrayTunnelProbeRunner $tunnelProbe,
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
            throw new RuntimeException('Не удалось преобразовать протокол входящего узла в ссылку клиента.');
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

        return $this->tunnelProbe->runProfile($connectV2Profile);
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

        return $this->tunnelProbe->runProfile($profile, 5);
    }

    /** @param array<string, mixed> $profile @return array{status: string, results: array<string, mixed>, error: ?string} */
    public function checkGeneratedProfile(array $profile): array
    {
        if (! is_array($profile['outbounds'] ?? null) || $profile['outbounds'] === []) {
            throw new RuntimeException('В созданном профиле подписки нет исходящих подключений Xray.');
        }

        return $this->tunnelProbe->runProfile($profile, 5);
    }

    private function buildConnectLink(NormalizedNode $node): string
    {
        $result = $this->builderFactory->make('uri')->build([$node]);
        $decoded = base64_decode($result->content, true);
        $uri = is_string($decoded) ? trim($decoded) : '';

        if ($uri === '' || str_contains($uri, "\n")) {
            throw new RuntimeException('Не удалось получить ссылку клиента из подписки /connect.');
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
            throw new RuntimeException('В созданном профиле подписки нет исходящего подключения Xray.');
        }

        return $profile;
    }

    private function binary(): string
    {
        return trim((string) config('services.inbound_health.xray_binary', 'xray'));
    }
}
