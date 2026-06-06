<?php

namespace App\Services;

use App\Entities\VlessConfig as VlessConfigData;
use App\Models\ShadowsocksConfig;
use App\Models\Server;
use App\Models\User;
use App\Models\VlessConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class XuiConfigService
{
    private ?string $session = null;
    private ?string $csrf = null;

    public function __construct(
        protected readonly Server $server,
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getVlessInbounds(): array
    {
        return $this->filterAllowedInbounds($this->getAllVlessInbounds());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllVlessInbounds(): array
    {
        return collect($this->getInbounds())
            ->map(fn (array $row) => $this->normalizeInbound($row))
            ->filter(fn (array $row) => $this->isSupportedVlessInboundProtocol($row['protocol'] ?? null) && ! empty($row['id']))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllShadowsocksInbounds(): array
    {
        return collect($this->getInbounds())
            ->map(fn (array $row) => $this->normalizeInbound($row))
            ->filter(fn (array $row) => ($row['protocol'] ?? null) === 'shadowsocks' && ! empty($row['id']))
            ->values()
            ->all();
    }

    public function addClient(int $inboundId, string $telegram, array $clientSettings = []): array
    {
        $settings = array_merge($this->getConfigSettings($telegram), $clientSettings);

        return $this->postClientSettings($inboundId, $settings);
    }

    public function addShadowsocksClient(int $inboundId, array $clientSettings): array
    {
        return $this->postClientSettings($inboundId, $clientSettings);
    }

    /**
     * Build a 3x-ui client payload that targets exactly one inbound.
     *
     * Newer panel versions accept a top-level `client` object with `inboundIds`,
     * while older versions still expect the client nested under `settings.clients`.
     * We keep the client shape shared so version-specific services only need to
     * choose the transport endpoint.
     *
     * @return array<string, mixed>
     */
    protected function buildSingleInboundClientPayload(int $inboundId, array $settings): array
    {
        return [
            'client' => array_merge($this->getDefaultClientSettings(), array_filter($settings, fn (mixed $value) => $value !== null)),
            'inboundIds' => [$inboundId],
        ];
    }

    protected function postClientSettings(int $inboundId, array $settings): array
    {
        $payload = [
            'id' => $inboundId,
            'settings' => json_encode([
                'clients' => [array_filter($settings, fn (mixed $value) => $value !== null)],
            ], JSON_UNESCAPED_SLASHES),
        ];

        $response = $this->postWithFallback([
            '/panel/api/inbounds/addClient',
            '/panel/inbound/addClient',
        ], $payload);

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    public function createClientOnFirstAvailableInbound(User $user): VlessConfig
    {
        $inbound = collect($this->getVlessInbounds())->first();

        if (! $inbound) {
            throw new RuntimeException("No VLESS inbound available for server [{$this->server->id}]");
        }

        return $this->createClientOnInbound($user, $inbound);
    }

    public function createClientOnInboundId(User $user, int $inboundId): VlessConfig
    {
        return $this->createClientOnInboundIdFromList($user, $inboundId, $this->getVlessInbounds());
    }

    public function createClientOnAnyInboundId(User $user, int $inboundId): VlessConfig
    {
        return $this->createClientOnInboundIdFromList($user, $inboundId, $this->getAllVlessInbounds());
    }

    public function createShadowsocksClientOnAnyInboundId(User $user, int $inboundId): ShadowsocksConfig
    {
        $inbound = collect($this->getAllShadowsocksInbounds())
            ->first(fn (array $row) => (int) ($row['id'] ?? 0) === $inboundId);

        if (! $inbound) {
            throw new RuntimeException("Shadowsocks inbound [{$inboundId}] not found for server [{$this->server->id}]");
        }

        $settings = $this->getShadowsocksConfigSettings((string) $user->telegram, $inbound);

        $this->addShadowsocksClient($inboundId, $settings);

        $attributes = [
            'server_id' => $this->server->id,
            'inbound_id' => $inboundId,
            'user_id' => $user->id,
            'name' => $settings['email'],
            'description' => null,
            'is_active' => true,
            'enable' => (bool) ($settings['enable'] ?? true),
            'port' => $inbound['port'] ?? null,
            'method' => $settings['method'] ?? $inbound['method'] ?? 'chacha20-ietf-poly1305',
            'password' => $settings['password'] ?? null,
            'plugin' => null,
            'plugin_opts' => null,
            'network' => $inbound['type'] ?? null,
            'security' => $inbound['security'] ?? null,
            'host' => $inbound['host'] ?? null,
            'path' => $inbound['path'] ?? null,
        ];

        return ShadowsocksConfig::query()->create($attributes);
    }

    /**
     * @param  array<int, array<string, mixed>>  $inbounds
     */
    private function createClientOnInboundIdFromList(User $user, int $inboundId, array $inbounds): VlessConfig
    {
        $inbound = collect($inbounds)
            ->first(fn (array $row) => (int) ($row['id'] ?? 0) === $inboundId);

        if (! $inbound) {
            throw new RuntimeException("VLESS inbound [{$inboundId}] not found for server [{$this->server->id}]");
        }

        return $this->createClientOnInbound($user, $inbound);
    }

    /**
     * @return array<int, VlessConfig>
     */
    public function createClientsOnAllowedInbounds(User $user): array
    {
        $allowedInbounds = $this->getVlessInbounds();

        if ($allowedInbounds === []) {
            return [];
        }

        $existingConfigs = $user->vlessConfigs()
            ->where('server_id', $this->server->id)
            ->get(['id', 'inbound_id', 'type']);

        return collect($allowedInbounds)
            ->reject(function (array $inbound) use ($existingConfigs) {
                return $existingConfigs->contains(function (VlessConfig $config) use ($inbound) {
                    $inboundId = (int) ($inbound['id'] ?? 0);

                    if (! empty($config->inbound_id)) {
                        return (int) $config->inbound_id === $inboundId;
                    }

                    return mb_strtolower((string) $config->type) === mb_strtolower((string) ($inbound['type'] ?? ''));
                });
            })
            ->map(fn (array $inbound) => $this->createClientOnInbound($user, $inbound))
            ->values()
            ->all();
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
            ->post('/panel/api/inbounds/updateClient/' . $uuid, [
                'id' => $client['inbound_id'],
                'settings' => json_encode([
                    'clients' => [$client['settings']],
                ], JSON_UNESCAPED_SLASHES),
            ]);

        if ($response->status() === 404) {
            $response = $this->getRequest()
                ->asForm()
                ->post('/panel/inbound/updateClient/' . $uuid, [
                    'id' => $client['inbound_id'],
                    'settings' => json_encode([
                        'clients' => [$client['settings']],
                    ], JSON_UNESCAPED_SLASHES),
                ]);
        }

        $response->throw();

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
            ->get('/panel/api/inbounds/getClientTraffics/' . urlencode($email));

        if ($response->status() === 404) {
            $response = $this->getRequest()
                ->get('/panel/inbound/getClientTraffics/' . urlencode($email));
        }

        $response->throw();

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    private function createClientOnInbound(User $user, array $inbound): VlessConfig
    {
        $inboundId = (int) ($inbound['id'] ?? 0);

        if ($inboundId <= 0) {
            throw new RuntimeException("VLESS inbound does not contain id for server [{$this->server->id}]");
        }

        $settings = $this->getConfigSettings((string) $user->telegram, $inbound);

        $this->addClient($inboundId, (string) $user->telegram, $settings);

        $attributes = $this->buildLocalConfigAttributes($inbound, $settings, $user->id);

        return VlessConfig::query()->updateOrCreate([
            'server_id' => $this->server->id,
            'uuid' => $attributes['uuid'],
        ], $attributes);
    }

    private function setSession(): void
    {
        $this->setStartSessionAndCsrf();

        $response = $this->getRequest()
            ->asForm()
            ->post('/login', [
                'username' => $this->server->panel_username,
                'password' => $this->server->panel_password,
            ])
            ->throw();

        $cookie = $this->getAuthorizationCookie($response);

        if (empty($cookie)) {
            throw new RuntimeException("Unable to authenticate with 3x-ui for server [{$this->server->id}]");
        }

        $this->session = $cookie;
    }

    public function getSession(): ?string
    {
        return $this->session;
    }

    private function setStartSessionAndCsrf(): void
    {
        $response = $this->getRequest()->get('/');

        $this->csrf = $this->getCsrfToken($response);
        $this->session = $this->getAuthorizationCookie($response);
    }

    private function getAuthorizationCookie(Response $response): ?string
    {
        $cookie = $response->cookies()->getCookieByName('3x-ui')
            ?? $response->cookies()->getCookieByName('x-ui');

        return $cookie?->getValue();
    }

    private function getCsrfToken(Response $response): ?string
    {
        $html = $response->body();

        preg_match('/meta name="csrf-token" content="([^"]+)"/', $html, $matches);

        return $matches[1] ?? null;
    }

    protected function getRequest(): PendingRequest
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'Origin' => rtrim($this->server->panel_link, '/'),
            'Referer' => rtrim($this->server->panel_link, '/') . '/',
            'User-Agent' => 'Mozilla/5.0',
        ];

        if ($this->session) {
            $headers['Cookie'] = '3x-ui=' . $this->session;
            $headers['Set-Cookie'] = '3x-ui=' . $this->session;
        }

        if ($this->csrf) {
            $headers['X-CSRF-Token'] = $this->csrf;
        }

        $options = [];

        if (config('telegram.proxy')) {
            $options['proxy'] = config('telegram.proxy');
        }

        return Http::baseUrl($this->getBaseUrl())
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

    private function getConfigSettings(string $telegram, array $inbound = []): array
    {
        $nextConfigId = ((int) VlessConfig::query()
            ->whereServerId($this->server->id)
            ->latest('id')
            ->value('id')) + 1;

        $flow = $this->shouldUseVisionFlow($inbound) ? 'xtls-rprx-vision' : null;

        return array_filter(array_merge($this->getDefaultClientSettings(), [
            'id' => (string) Str::uuid(),
            'flow' => $flow,
            'email' => sprintf(
                '%s_%s_%d',
                ltrim($telegram, '@'),
                Str::slug(Str::snake($this->server->name), '_'),
                $nextConfigId,
            ),
            'enable' => true,
            'subId' => Str::lower(Str::random(16)),
            'password' => Str::lower(Str::random(16)),
            'auth' => Str::lower(Str::random(16)),
        ]), fn (mixed $value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultClientSettings(): array
    {
        return [
            'email' => '',
            'subId' => '',
            'id' => '',
            'password' => '',
            'auth' => '',
            'flow' => '',
            'security' => 'auto',
            'totalGB' => 0,
            'expiryTime' => 0,
            'reset' => 0,
            'limitIp' => 0,
            'tgId' => 0,
            'group' => '',
            'comment' => '',
            'enable' => true,
        ];
    }

    private function getShadowsocksConfigSettings(string $telegram, array $inbound = []): array
    {
        $nextConfigId = ((int) ShadowsocksConfig::query()
            ->whereServerId($this->server->id)
            ->latest('id')
            ->value('id')) + 1;

        $method = $inbound['method']
            ?? $inbound['settings']['method']
            ?? $inbound['settings']['clients'][0]['method']
            ?? 'chacha20-ietf-poly1305';

        return [
            'method' => $method,
            'password' => Str::random(24),
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
        ];
    }

    private function shouldUseVisionFlow(array $inbound): bool
    {
        return ($inbound['type'] ?? null) === 'tcp'
            && ($inbound['security'] ?? null) === 'reality';
    }

    private function isSupportedVlessInboundProtocol(?string $protocol): bool
    {
        return in_array(mb_strtolower((string) $protocol), ['vless', 'hysteria', 'hysteria2', 'hy2'], true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $inbounds
     * @return array<int, array<string, mixed>>
     */
    private function filterAllowedInbounds(array $inbounds): array
    {
        $allowedInboundIds = $this->server->getAllowedInboundIds();

        if ($allowedInboundIds === []) {
            return [];
        }

        return collect($inbounds)
            ->filter(fn (array $row) => in_array((int) $row['id'], $allowedInboundIds, true))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function normalizeInbound(array $row): array
    {
        $settings = $this->decodeJsonField($row['settings'] ?? null);
        $streamSettings = $this->decodeJsonField($row['streamSettings'] ?? $row['stream_settings'] ?? null);
        $wsSettings = $this->decodeJsonField($streamSettings['wsSettings'] ?? $streamSettings['ws_settings'] ?? null);
        $grpcSettings = $this->decodeJsonField($streamSettings['grpcSettings'] ?? $streamSettings['grpc_settings'] ?? null);
        $tlsSettings = $this->decodeJsonField($streamSettings['tlsSettings'] ?? $streamSettings['tls_settings'] ?? null);
        $realitySettings = $this->decodeJsonField(
            $streamSettings['realitySettings'] ?? $streamSettings['reality_settings'] ?? null
        );

        $headers = $this->decodeJsonField($wsSettings['headers'] ?? null);
        $type = mb_strtolower((string) ($streamSettings['network'] ?? 'tcp'));
        $security = mb_strtolower((string) ($streamSettings['security'] ?? 'none'));

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'protocol' => $row['protocol'] ?? null,
            'port' => isset($row['port']) ? (int) $row['port'] : null,
            'type' => $type,
            'security' => $security,
            'settings' => $settings,
            'stream_settings' => $streamSettings,
            'method' => $settings['method'] ?? $settings['clients'][0]['method'] ?? null,
            'pbk' => $realitySettings['settings']['publicKey'] ?? null,
            'fp' => $realitySettings['settings']['fingerprint'] ?? null,
            'sni' => $realitySettings['serverNames'][0]
                ?? $tlsSettings['serverName']
                ?? $headers['Host']
                ?? null,
            'sid' => $realitySettings['shortIds'][0] ?? null,
            'spx' => '/',
            'host' => $headers['Host'] ?? null,
            'path' => $wsSettings['path'] ?? null,
            'service_name' => $grpcSettings['serviceName'] ?? $grpcSettings['service_name'] ?? null,
        ];
    }

    public function buildLocalConfigAttributes(array $inbound, array $settings, ?int $userId = null): array
    {
        $config = new VlessConfigData(
            $this->server->id,
            $inbound['id'] ?? null,
            $userId,
            $settings['email'] ?? null,
            null,
            true,
            (bool) ($settings['enable'] ?? true),
            $settings['id'] ?? null,
            $settings['subId'] ?? null,
            $settings['password'] ?? null,
            $settings['auth'] ?? null,
            $inbound['port'] ?? null,
            $inbound['type'] ?? null,
            'none',
            $inbound['security'] ?? null,
            $settings['flow'] ?? null,
            $inbound['pbk'] ?? null,
            $inbound['fp'] ?? null,
            $inbound['sni'] ?? null,
            $inbound['host'] ?? null,
            $inbound['path'] ?? null,
            $inbound['service_name'] ?? null,
            $inbound['sid'] ?? null,
            $inbound['spx'] ?? '/'
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
            'settings' => array_filter([
                'id' => $client['uuid'] ?? $config->uuid,
                'flow' => $config->flow,
                'email' => $client['email'] ?? $config->name,
                'limitIp' => 0,
                'totalGB' => $client['total'] ?? 0,
                'expiryTime' => $client['expiryTime'] ?? 0,
                'enable' => $enabled,
                'tgId' => '',
                'subId' => $client['subId'] ?? $config->sub_id,
                'password' => $client['password'] ?? $config->password,
                'auth' => $client['auth'] ?? $config->auth,
                'comment' => '',
                'reset' => $client['reset'] ?? 0,
            ], fn (mixed $value) => $value !== null),
        ];
    }

    private function getBaseUrl(): string
    {
        return rtrim($this->server->panel_link, '/');
    }

    protected function postWithFallback(array $paths, array $payload): Response
    {
        $lastException = null;

        foreach ($paths as $index => $path) {
            try {
                return $this->getRequest()
                    ->asForm()
                    ->post($path, $payload)
                    ->throw();
            } catch (RequestException $exception) {
                $lastException = $exception;

                if ($exception->response?->status() !== 404 || $index === array_key_last($paths)) {
                    throw $exception;
                }
            }
        }

        throw $lastException ?? new RuntimeException('Unable to complete XUI request.');
    }

}
