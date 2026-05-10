<?php

namespace App\Services;

use App\Entities\VlessConfig as VlessConfigData;
use App\Models\Server;
use App\Models\User;
use App\Models\VlessConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class XuiConfigService
{
    private ?string $session = null;

    public function __construct(
        private readonly Server $server,
    )
    {
        $this->setSession();
    }

    public function getInbounds(): array
    {
        $inboundsResponse = $this->getRequest()
            ->get('/panel/api/inbounds/list')
            ->throw();

        return $this->normalizeResponseData($inboundsResponse->json());
    }

    public function addClient(int $inboundId, string $telegram, array $clientSettings = []): array
    {
        $settings = array_merge($this->getConfigSettings($telegram), $clientSettings);

        $response = $this->getRequest()
            ->asForm()
            ->post('/panel/api/inbounds/addClient', [
                'id' => $inboundId,
                'settings' => json_encode([
                    'clients' => [$settings],
                ], JSON_UNESCAPED_SLASHES),
            ])
            ->throw();

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    public function createClientOnFirstAvailableInbound(User $user): VlessConfig
    {
        $inbound = collect($this->getInbounds())
            ->first(fn (array $row) => ($row['protocol'] ?? null) === 'vless');

        if (! $inbound) {
            throw new RuntimeException("No VLESS inbound available for server [{$this->server->id}]");
        }

        $inboundId = $inbound['id'] ?? null;

        if (! $inboundId) {
            throw new RuntimeException("VLESS inbound does not contain id for server [{$this->server->id}]");
        }

        $settings = $this->getConfigSettings((string) $user->telegram);

        $this->addClient((int) $inboundId, (string) $user->telegram, $settings);

        $attributes = $this->buildLocalConfigAttributes($inbound, $settings, $user->id);

        return VlessConfig::query()->updateOrCreate([
            'server_id' => $this->server->id,
            'uuid' => $attributes['uuid'],
        ], $attributes);
    }

    public function setClientEnabled(string $uuid, bool $enabled): array
    {
        $config = VlessConfig::query()
            ->whereServerId($this->server->id)
            ->where('uuid', $uuid)
            ->first();

        if (! $config) {
            throw new RuntimeException("Client [{$uuid}] was not found in local configs for server [{$this->server->id}]");
        }

        $traffic = $this->getClientTraffics($config->name);
        $client = $this->extractClientFromTrafficPayload($traffic, $config, $enabled);

        $response = $this->getRequest()
            ->asForm()
            ->post("/panel/api/inbounds/updateClient/{$uuid}", [
                'id' => $client['inbound_id'],
                'settings' => json_encode([
                    'clients' => [$client['settings']],
                ], JSON_UNESCAPED_SLASHES),
            ])
            ->throw();

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    public function enableClient(string $uuid): array
    {
        return $this->setClientEnabled($uuid, true);
    }

    public function disableClient(string $uuid): array
    {
        return $this->setClientEnabled($uuid, false);
    }

    public function getClientTraffics(string $email): array
    {
        $response = $this->getRequest()
            ->get('/panel/api/inbounds/getClientTraffics/' . urlencode($email))
            ->throw();

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    private function setSession(): void
    {
        $response = $this->getRequest()
            ->asForm()
            ->post('/login', [
                'username' => $this->server->panel_username,
                'password' => $this->server->panel_password,
            ])
            ->throw();

        $cookie = $response->cookies()->getCookieByName('3x-ui')
            ?? $response->cookies()->getCookieByName('x-ui');

        if (! $cookie?->getValue()) {
            throw new RuntimeException("Unable to authenticate with 3x-ui for server [{$this->server->id}]");
        }

        $this->session = $cookie?->getValue();
    }

    public function getSession(): ?string
    {
        return $this->session;
    }

    private function getRequest(): PendingRequest
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($this->session) {
            $headers['Cookie'] = '3x-ui=' . $this->session;
        }

        $options = [];

        if (config('telegram.proxy')) {
            $options['proxy'] = config('telegram.proxy');
        }

        return Http::baseUrl(rtrim($this->server->panel_link, '/'))
            ->timeout(15)
            ->withHeaders($headers)
            ->withOptions($options);
    }

    private function normalizeResponseData(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $data = $payload['obj'] ?? $payload['data'] ?? $payload;

        return is_array($data) ? array_values($data) : [];
    }

    private function getConfigSettings(string $telegram): array
    {
        $nextConfigId = ((int) VlessConfig::query()
            ->whereServerId($this->server->id)
            ->latest('id')
            ->value('id')) + 1;

        return [
            'id' => (string) Str::uuid(),
            'flow' => 'xtls-rprx-vision',
            'email' => sprintf(
                '%s_%s_%d',
                ltrim($telegram, '@'),
                Str::slug(Str::snake($this->server->name), '_'),
                $nextConfigId,
            ),
            'limitIp' => 0,
            'totalGB' => 0,
            'expiryTime' => 0,
            'enable' => true,
            'tgId' => '',
            'subId' => Str::lower(Str::random(16)),
            'comment' => '',
            'reset' => 0,
        ];
    }

    private function buildLocalConfigAttributes(array $inbound, array $settings, ?int $userId = null): array
    {
        $streamSettings = $this->decodeJsonField(
            $inbound['streamSettings'] ?? $inbound['stream_settings'] ?? null
        );

        $config = new VlessConfigData(
            $this->server->id,
            $userId,
            $settings['email'] ?? null,
            null,
            true,
            (bool) ($settings['enable'] ?? true),
            $settings['id'] ?? null,
            $settings['subId'] ?? null,
            $inbound['port'] ?? null,
            $streamSettings['network'] ?? null,
            'none',
            $streamSettings['security'] ?? null,
            $settings['flow'] ?? null,
            $streamSettings['realitySettings']['settings']['publicKey'] ?? null,
            $streamSettings['realitySettings']['settings']['fingerprint'] ?? null,
            $streamSettings['realitySettings']['serverNames'][0] ?? null,
            $streamSettings['realitySettings']['shortIds'][0] ?? null,
            '/'
        );

        return $config->toArray();
    }

    private function decodeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function extractClientFromTrafficPayload(array $payload, VlessConfig $config, bool $enabled): array
    {
        $client = $payload['obj'] ?? null;

        if (! is_array($client)) {
            throw new RuntimeException("Traffic payload for client [{$config->name}] is invalid on server [{$this->server->id}]");
        }

        $inboundId = $client['inboundId'] ?? null;

        if (! $inboundId) {
            throw new RuntimeException("Traffic payload for client [{$config->name}] does not contain inboundId");
        }

        return [
            'inbound_id' => $inboundId,
            'settings' => [
                'id' => $client['uuid'] ?? $config->uuid,
                'flow' => $config->flow,
                'email' => $client['email'] ?? $config->name,
                'limitIp' => 0,
                'totalGB' => $client['total'] ?? 0,
                'expiryTime' => $client['expiryTime'] ?? 0,
                'enable' => $enabled,
                'tgId' => '',
                'subId' => $client['subId'] ?? $config->sub_id,
                'comment' => '',
                'reset' => $client['reset'] ?? 0,
            ],
        ];
    }
}
