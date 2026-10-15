<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Server;
use App\Models\VlessConfig;
use App\Models\XrayInbound;
use App\Models\XrayInboundHealthCheck;
use Illuminate\Support\Str;
use RuntimeException;

final class XrayInboundProbeProvisioner
{
    private const SUPPORTED_PROTOCOLS = ['vless', 'trojan', 'shadowsocks', 'hysteria', 'hysteria2', 'hy2'];

    public function __construct(
        private readonly SingBoxInboundProbeRunner $probeRunner,
    ) {}

    /** @return array{status: string, results: array<string, mixed>, error: ?string} */
    public function check(XrayInbound $inbound): array
    {
        $protocol = mb_strtolower((string) data_get($inbound->params, 'protocol', ''));

        if (in_array($protocol, ['amneziawg', 'wireguard'], true)) {
            return $this->unsupported('Full tunnel probing for WireGuard/AmneziaWG is not available in this runner.');
        }

        if (! in_array($protocol, self::SUPPORTED_PROTOCOLS, true)) {
            return $this->unsupported("Full tunnel probing for {$protocol} inbounds is not supported.");
        }

        if (! $this->probeRunner->isAvailable()) {
            return $this->unsupported('sing-box client binary is not installed.');
        }

        $server = $inbound->server;

        if (! $server instanceof Server || ! $server->is_active || ! $server->is_ready) {
            return $this->unsupported('Owning server is not active and ready.');
        }

        return $this->probeThroughInbound($server, $inbound);
    }

    /** @return array{status: string, results: array<string, mixed>, error: ?string} */
    private function probeThroughInbound(Server $server, XrayInbound $inbound): array
    {
        $xui = XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server);
        $remoteInbound = collect($xui->getInbounds())
            ->map(fn (array $row): array => $xui->normalizeInbound($row))
            ->first(fn (array $row): bool => (int) ($row['id'] ?? 0) === (int) $inbound->external_id);

        if (! is_array($remoteInbound)) {
            throw new RuntimeException('Inbound is missing from the remote 3x-ui panel.');
        }

        $healthRecord = $inbound->healthCheck ?? XrayInboundHealthCheck::query()->firstOrCreate([
            'xray_inbound_id' => $inbound->id,
        ]);
        $healthRecord->probe_client_id ??= (string) Str::uuid();
        $healthRecord->probe_client_email ??= 'health-check-'.$inbound->id;
        $healthRecord->save();
        $clientId = (string) $healthRecord->probe_client_id;
        $email = (string) $healthRecord->probe_client_email;
        $settings = $this->probeClientSettings($remoteInbound, $clientId, $email);
        $existingClient = collect(data_get($remoteInbound, 'settings.clients', []))
            ->first(fn (mixed $client): bool => is_array($client) && (string) ($client['email'] ?? '') === $email);

        if (is_array($existingClient)) {
            $settings = [...$settings, ...$existingClient];
            $healthRecord->probe_client_id = (string) ($existingClient['id'] ?? $clientId);
            $healthRecord->save();
        } else {
            $addResponse = $xui->addClient((int) $inbound->external_id, $email, $settings);

            if (($addResponse['success'] ?? true) !== true) {
                throw new RuntimeException((string) ($addResponse['msg'] ?? 'Panel rejected the health-check client.'));
            }

            $remoteInbound = collect($xui->getInbounds())
                ->map(fn (array $row): array => $xui->normalizeInbound($row))
                ->first(fn (array $row): bool => (int) ($row['id'] ?? 0) === (int) $inbound->external_id);
            $createdClient = collect(data_get($remoteInbound, 'settings.clients', []))
                ->first(fn (mixed $client): bool => is_array($client) && (string) ($client['email'] ?? '') === $email);

            if (! is_array($createdClient)) {
                throw new RuntimeException('Panel accepted the health-check client but did not return it on the inbound.');
            }

            $settings = [...$settings, ...$createdClient];
            $healthRecord->probe_client_id = (string) ($createdClient['id'] ?? $clientId);
            $healthRecord->save();
        }

        $attributes = $xui->buildLocalConfigAttributes($remoteInbound, $settings);
        $config = new VlessConfig($attributes);
        $config->setRelation('server', $server);

        return $this->probeRunner->check($config, $server, $inbound);
    }

    /** @param array<string, mixed> $remoteInbound @return array<string, mixed> */
    private function probeClientSettings(array $remoteInbound, string $clientId, string $email): array
    {
        $protocol = mb_strtolower((string) ($remoteInbound['protocol'] ?? ''));
        $firstClient = data_get($remoteInbound, 'settings.clients.0', []);
        $flow = is_array($firstClient) ? (string) ($firstClient['flow'] ?? '') : '';

        if ($flow === '' && $protocol === 'vless' && ($remoteInbound['security'] ?? null) === 'reality') {
            $flow = 'xtls-rprx-vision';
        }

        return array_filter([
            'id' => $clientId,
            'email' => $email,
            'flow' => $flow !== '' ? $flow : null,
            'password' => Str::random(32),
            'auth' => Str::random(32),
            'subId' => Str::lower(Str::random(16)),
            'enable' => true,
            'totalGB' => 0,
            'expiryTime' => 0,
            'comment' => 'Managed by inbound health checks',
        ], fn (mixed $value): bool => $value !== null);
    }

    /** @return array{status: string, results: array<string, mixed>, error: string} */
    private function unsupported(string $reason): array
    {
        return [
            'status' => XrayInboundHealthCheck::STATUS_UNSUPPORTED,
            'results' => [],
            'error' => $reason,
        ];
    }
}
