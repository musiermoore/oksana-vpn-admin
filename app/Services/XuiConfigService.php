<?php

namespace App\Services;

use App\Entities\VlessConfig as VlessConfigData;
use App\Models\Server;
use App\Models\ShadowsocksConfig;
use App\Models\User;
use App\Models\VlessConfig;
use App\Services\WireGuardSubscriptionLinkService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class XuiConfigService
{
    private ?string $session = null;

    private ?string $csrf = null;

    public function __construct(
        protected readonly Server $server,
    ) {
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
            ->get();

        return collect($allowedInbounds)
            ->map(function (array $inbound) use ($existingConfigs, $user) {
                $existingConfig = $existingConfigs->first(function (VlessConfig $config) use ($inbound) {
                    $inboundId = (int) ($inbound['id'] ?? 0);

                    if (! empty($config->inbound_id)) {
                        return (int) $config->inbound_id === $inboundId;
                    }

                    return mb_strtolower((string) $config->type) === mb_strtolower((string) ($inbound['type'] ?? ''));
                });

                if ($existingConfig instanceof VlessConfig) {
                    return $this->refreshExistingClientConfig($existingConfig, $user, $inbound);
                }

                return $this->createClientOnInbound($user, $inbound);
            })
            ->values()
            ->all();
    }

    private function refreshExistingClientConfig(VlessConfig $config, User $user, array $inbound): VlessConfig
    {
        $clients = $inbound['settings']['clients'] ?? [];

        if (! is_array($clients)) {
            return $config;
        }

        $matchedClient = collect($clients)->first(function (mixed $client) use ($config, $user): bool {
            if (! is_array($client)) {
                return false;
            }

            $clientId = (string) ($client['id'] ?? '');
            $clientEmail = (string) ($client['email'] ?? '');

            return ($config->uuid && $clientId === (string) $config->uuid)
                || ($config->name && $clientEmail === (string) $config->name)
                || ($user->telegram && $clientEmail === (string) $user->telegram);
        });

        if (! is_array($matchedClient)) {
            return $config;
        }

        $attributes = $this->buildLocalConfigAttributes($inbound, [
            'email' => $matchedClient['email'] ?? $config->name,
            'enable' => array_key_exists('enable', $matchedClient) ? (bool) $matchedClient['enable'] : $config->enable,
            'id' => $matchedClient['id'] ?? $config->uuid,
            'subId' => $matchedClient['subId'] ?? $config->sub_id,
            'password' => $matchedClient['password'] ?? $config->password,
            'auth' => $matchedClient['auth'] ?? $config->auth,
            'flow' => $matchedClient['flow'] ?? $config->flow,
        ], $user->id);

        $config->fill($attributes);
        $config->save();

        return $config->fresh();
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

        $client = $this->resolveClientForEnableState($config, $enabled);
        $response = $this->updateClient($config, $client);

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
        $response = $this->getWithFallback($this->getClientTrafficPaths($email));

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * Send an arbitrary authenticated request to the panel using the same
     * session, cookies, and CSRF flow as the rest of the XUI integration.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendDiagnosticRequest(string $method, string $path, array $payload = [], string $encoding = 'form'): array
    {
        $method = mb_strtolower(trim($method));
        $path = $this->normalizeDiagnosticPath($path);
        $request = $this->getRequest();

        if ($encoding === 'form') {
            $request = $request->asForm();
        }

        $response = match ($method) {
            'get' => $request->get($path, $payload),
            'post' => $request->post($path, $payload),
            'put' => $request->put($path, $payload),
            'patch' => $request->patch($path, $payload),
            'delete' => $request->delete($path, $payload),
            default => throw new RuntimeException("Unsupported diagnostic request method [{$method}]"),
        };

        $json = $response->json();

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->body(),
            'json' => is_array($json) ? $json : null,
            'request' => [
                'method' => mb_strtoupper($method),
                'path' => $path,
                'encoding' => $encoding,
                'payload' => $payload,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getClientTrafficSummaries(): array
    {
        try {
            $rows = $this->getClientListEntries();

            return collect($rows)
                ->map(function (array $row): array {
                    $traffic = is_array($row['traffic'] ?? null) ? $row['traffic'] : [];
                    $inboundIds = is_array($row['inboundIds'] ?? null) ? $row['inboundIds'] : [];

                    return [
                        'inbound_id' => (int) ($inboundIds[0] ?? 0),
                        'protocol' => (string) ($row['protocol'] ?? ''),
                        'email' => (string) ($row['email'] ?? ''),
                        'upload_bytes' => (int) ($traffic['up'] ?? $traffic['upload'] ?? $traffic['uploadBytes'] ?? 0),
                        'download_bytes' => (int) ($traffic['down'] ?? $traffic['download'] ?? $traffic['downloadBytes'] ?? 0),
                    ];
                })
                ->filter(fn (array $row) => $row['email'] !== '')
                ->values()
                ->all();
        } catch (RequestException $exception) {
            if ($exception->response?->status() !== 404) {
                throw $exception;
            }
        }

        $rows = collect($this->getInbounds())
            ->flatMap(function (array $row): array {
                $inbound = $this->normalizeInbound($row);
                $stats = $this->normalizeClientStats($row['clientStats'] ?? $row['client_stats'] ?? $inbound['client_stats'] ?? []);

                return collect($stats)
                    ->map(function (array $stat) use ($inbound): array {
                        return [
                            'inbound_id' => (int) ($inbound['id'] ?? 0),
                            'protocol' => (string) ($inbound['protocol'] ?? ''),
                            'email' => (string) ($stat['email'] ?? ''),
                            'upload_bytes' => (int) ($stat['up'] ?? $stat['upload'] ?? $stat['uploadBytes'] ?? 0),
                            'download_bytes' => (int) ($stat['down'] ?? $stat['download'] ?? $stat['downloadBytes'] ?? 0),
                        ];
                    })
                    ->filter(fn (array $row) => $row['email'] !== '')
                    ->values()
                    ->all();
            })
            ->values()
            ->all();

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOnlineClientEntries(): array
    {
        $response = $this->postWithFallback($this->getOnlineClientPaths(), []);
        $payload = $response->json();
        $rows = $this->normalizeResponseData($payload);

        if ($rows !== []) {
            return collect($rows)
                ->flatMap(fn (mixed $row) => $this->expandOnlineEntry($row))
                ->values()
                ->all();
        }

        $fallbackRows = $payload['obj'] ?? $payload['data'] ?? [];
        return collect($fallbackRows)
            ->flatMap(fn (mixed $row) => $this->expandOnlineEntry($row))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string|array<string, mixed>>
     */
    public function getClientIps(string $email): array
    {
        $response = $this->getRequest()
            ->post('/panel/api/clients/ips/'.urlencode($email))
            ->throw();

        $payload = $response->json();
        $rows = $payload['obj'] ?? $payload['data'] ?? [];

        if (is_string($rows)) {
            $rows = preg_split('/[\r\n,]+/', $rows) ?: [];
        }

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $value): string|array|null {
            if (is_string($value)) {
                $value = trim($value);

                return $value !== '' ? $value : null;
            }

            if (! is_array($value)) {
                return null;
            }

            $ip = trim((string) ($value['ip'] ?? ''));

            if ($ip === '') {
                return null;
            }

            return [
                'ip' => $ip,
                'time' => $value['time'] ?? $value['last_seen'] ?? $value['lastSeen'] ?? null,
                'node' => $value['node'] ?? null,
            ];
        }, $rows)));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function setClientEnabledByIdentifier(
        string $identifier,
        string $email,
        int $inboundId,
        bool $enabled,
        array $context = [],
    ): array {
        $client = $this->extractClientFromInboundSettings(
            inboundId: $inboundId,
            identifier: $identifier,
            email: $email,
            enabled: $enabled,
            context: $context,
        );

        $response = $this->postUpdateClientByIdentifier($identifier, $client);
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

        $refreshedInbound = $this->findInboundById($inboundId) ?? $inbound;
        $resolvedSettings = $this->resolveClientSettingsAfterCreation($refreshedInbound, $settings);
        $attributes = $this->buildLocalConfigAttributes($refreshedInbound, $resolvedSettings, $user->id);

        return VlessConfig::query()->updateOrCreate(
            $this->resolveLocalConfigLookupAttributes($refreshedInbound, $attributes),
            $attributes,
        );
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
        $response = $this->getRequest()->get('/csrf-token');

        $this->csrf = $this->getCsrfToken($response);
        $this->session = $this->getAuthorizationCookie($response);

        if ($this->csrf) {
            return;
        }

        $response = $this->getRequest()->get('/');

        $this->csrf = $this->getCsrfToken($response);
        $this->session = $this->session ?: $this->getAuthorizationCookie($response);
    }

    private function getAuthorizationCookie(Response $response): ?string
    {
        $cookie = $response->cookies()->getCookieByName('3x-ui')
            ?? $response->cookies()->getCookieByName('x-ui');

        return $cookie?->getValue();
    }

    private function getCsrfToken(Response $response): ?string
    {
        $payload = $response->json();

        if (is_array($payload)) {
            $token = $payload['token']
                ?? $payload['csrf_token']
                ?? $payload['csrfToken']
                ?? (is_string($payload['obj'] ?? null) ? $payload['obj'] : null)
                ?? (is_string($payload['data'] ?? null) ? $payload['data'] : null)
                ?? (is_string($payload['obj']['token'] ?? null) ? $payload['obj']['token'] : null)
                ?? (is_string($payload['data']['token'] ?? null) ? $payload['data']['token'] : null);

            if (is_string($token) && trim($token) !== '') {
                return trim($token);
            }
        }

        $body = trim($response->body());

        if ($body !== '' && ! str_contains($body, '<html')) {
            return $body;
        }

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
            'Referer' => rtrim($this->server->panel_link, '/').'/',
            'User-Agent' => 'Mozilla/5.0',
        ];

        if ($this->session) {
            $headers['Cookie'] = '3x-ui='.$this->session;
            $headers['Set-Cookie'] = '3x-ui='.$this->session;
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

        if ($this->isWireGuardInbound($inbound)) {
            return array_filter([
                'email' => sprintf(
                    '%s_%s_%d',
                    ltrim($telegram, '@'),
                    Str::slug(Str::snake($this->server->name), '_'),
                    $nextConfigId,
                ),
                'totalGB' => 0,
                'expiryTime' => 0,
                'enable' => true,
            ], fn (mixed $value) => ! in_array($value, [null, ''], true));
        }

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
        return in_array(mb_strtolower((string) $protocol), ['vless', 'trojan', 'hysteria', 'hysteria2', 'hy2', 'wireguard'], true);
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
        $xhttpSettings = $this->decodeJsonField($streamSettings['xhttpSettings'] ?? $streamSettings['xhttp_settings'] ?? null);
        $tlsSettings = $this->decodeJsonField($streamSettings['tlsSettings'] ?? $streamSettings['tls_settings'] ?? null);
        $realitySettings = $this->decodeJsonField(
            $streamSettings['realitySettings'] ?? $streamSettings['reality_settings'] ?? null
        );

        $headers = $this->decodeJsonField($wsSettings['headers'] ?? null);
        $type = mb_strtolower((string) ($streamSettings['network'] ?? 'tcp'));
        $security = mb_strtolower((string) ($streamSettings['security'] ?? 'none'));
        $alpn = $this->normalizeAlpn($tlsSettings['alpn'] ?? $streamSettings['alpn'] ?? null);
        ['type' => $obfs, 'password' => $obfsPassword] = $this->extractHysteriaObfs($settings, $streamSettings);

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
            'alpn' => $alpn,
            'fp' => $realitySettings['settings']['fingerprint']
                ?? Arr::get($tlsSettings, 'settings.fingerprint')
                ?? $streamSettings['fp']
                ?? $settings['fp']
                ?? $row['fp']
                ?? null,
            'sni' => $realitySettings['serverNames'][0]
                ?? $tlsSettings['serverName']
                ?? $headers['Host']
                ?? null,
            'sid' => $realitySettings['shortIds'][0] ?? null,
            'spx' => '/',
            'host' => $xhttpSettings['host']
                ?? $headers['Host']
                ?? null,
            'path' => $xhttpSettings['path']
                ?? $wsSettings['path']
                ?? null,
            'service_name' => $grpcSettings['serviceName'] ?? $grpcSettings['service_name'] ?? null,
            'mode' => $xhttpSettings['mode'] ?? null,
            'obfs' => $obfs,
            'obfs_password' => $obfsPassword,
            'extra' => $this->normalizeJsonValueToString($xhttpSettings['extra'] ?? null),
            'x_padding_bytes' => isset($xhttpSettings['xPaddingBytes'])
                ? (string) $xhttpSettings['xPaddingBytes']
                : (isset($xhttpSettings['x_padding_bytes']) ? (string) $xhttpSettings['x_padding_bytes'] : null),
        ];
    }

    public function buildLocalConfigAttributes(array $inbound, array $settings, ?int $userId = null): array
    {
        if ($this->isWireGuardInbound($inbound)) {
            return $this->buildWireGuardLocalConfigAttributes($inbound, $settings, $userId);
        }

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
            $inbound['protocol'] ?? 'vless',
            $inbound['type'] ?? null,
            'none',
            $inbound['security'] ?? null,
            $settings['flow'] ?? null,
            $inbound['pbk'] ?? null,
            $inbound['alpn'] ?? null,
            $inbound['fp'] ?? null,
            $inbound['sni'] ?? null,
            $inbound['host'] ?? null,
            $inbound['path'] ?? null,
            $inbound['service_name'] ?? null,
            $inbound['mode'] ?? null,
            $inbound['obfs'] ?? null,
            $inbound['obfs_password'] ?? null,
            $inbound['extra'] ?? null,
            isset($inbound['x_padding_bytes']) ? (string) $inbound['x_padding_bytes'] : null,
            $inbound['sid'] ?? null,
            $inbound['spx'] ?? '/'
        );

        return $config->toArray();
    }

    /**
     * @param  array<string, mixed>  $inbound
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function buildWireGuardLocalConfigAttributes(array $inbound, array $settings, ?int $userId = null): array
    {
        $email = (string) ($settings['email'] ?? '');
        $identifier = $this->resolveWireGuardIdentifier($settings, $email);
        $subscriptionUri = app(WireGuardSubscriptionLinkService::class)->fromXui(
            $this->server,
            $inbound,
            $settings,
            $email !== '' ? $email : null,
        );

        return [
            'server_id' => $this->server->id,
            'inbound_id' => $inbound['id'] ?? null,
            'user_id' => $userId,
            'name' => $email !== '' ? $email : $identifier,
            'description' => null,
            'is_active' => true,
            'enable' => array_key_exists('enable', $settings) ? (bool) $settings['enable'] : true,
            'uuid' => $identifier,
            'sub_id' => null,
            'password' => $this->firstNonEmptyString([
                $settings['privateKey'] ?? null,
                $settings['private_key'] ?? null,
                $settings['secretKey'] ?? null,
                $settings['secret_key'] ?? null,
            ]),
            'auth' => $this->firstNonEmptyString([
                $settings['presharedKey'] ?? null,
                $settings['preshared_key'] ?? null,
                $settings['preSharedKey'] ?? null,
            ]),
            'port' => $inbound['port'] ?? null,
            'protocol' => 'wireguard',
            'type' => 'wireguard',
            'encryption' => 'none',
            'security' => 'none',
            'flow' => null,
            'pbk' => $this->firstNonEmptyString([
                $inbound['publicKey'] ?? null,
                $inbound['public_key'] ?? null,
                Arr::get($inbound, 'settings.publicKey'),
                Arr::get($inbound, 'settings.public_key'),
            ]),
            'alpn' => null,
            'fp' => null,
            'sni' => null,
            'host' => null,
            'path' => null,
            'service_name' => null,
            'mode' => null,
            'obfs' => null,
            'obfs_password' => null,
            'extra' => $subscriptionUri,
            'x_padding_bytes' => null,
            'sid' => null,
            'spx' => null,
        ];
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

    private function normalizeJsonValueToString(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value !== '' ? $value : null;
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded !== false ? $encoded : null;
        }

        return null;
    }

    private function normalizeAlpn(mixed $value): ?string
    {
        if (is_string($value)) {
            $normalized = trim($value);

            return $normalized !== '' ? $normalized : null;
        }

        if (! is_array($value)) {
            return null;
        }

        $normalized = collect($value)
            ->map(fn (mixed $item) => is_scalar($item) ? trim((string) $item) : '')
            ->filter()
            ->implode(',');

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $streamSettings
     * @return array{type: ?string, password: ?string}
     */
    private function extractHysteriaObfs(array $settings, array $streamSettings): array
    {
        $candidates = [
            Arr::get($settings, 'obfs'),
            Arr::get($settings, 'obfsSettings'),
            Arr::get($settings, 'obfs_settings'),
            Arr::get($streamSettings, 'obfs'),
            Arr::get($streamSettings, 'obfsSettings'),
            Arr::get($streamSettings, 'obfs_settings'),
            Arr::get($streamSettings, 'finalmask.udp.0'),
        ];

        $type = $this->firstNonEmptyString([
            Arr::get($settings, 'obfsType'),
            Arr::get($settings, 'obfs_type'),
            Arr::get($streamSettings, 'obfsType'),
            Arr::get($streamSettings, 'obfs_type'),
        ]);

        $password = $this->firstNonEmptyString([
            Arr::get($settings, 'obfsPassword'),
            Arr::get($settings, 'obfs_password'),
            Arr::get($streamSettings, 'obfsPassword'),
            Arr::get($streamSettings, 'obfs_password'),
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate)) {
                $type ??= trim($candidate) !== '' ? trim($candidate) : null;

                continue;
            }

            if (! is_array($candidate)) {
                continue;
            }

            $type ??= $this->firstNonEmptyString([
                Arr::get($candidate, 'type'),
                Arr::get($candidate, 'name'),
            ]);

            $password ??= $this->firstNonEmptyString([
                Arr::get($candidate, 'password'),
                Arr::get($candidate, 'settings.password'),
                Arr::get($candidate, 'salamander.password'),
                Arr::get($candidate, 'salamander.settings.password'),
            ]);
        }

        return [
            'type' => $type,
            'password' => $password,
        ];
    }

    private function firstNonEmptyString(array $values): ?string
    {
        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $normalized = trim((string) $value);

            if ($normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $inbound
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function resolveClientSettingsAfterCreation(array $inbound, array $settings): array
    {
        $email = (string) ($settings['email'] ?? '');
        $inboundId = (int) ($inbound['id'] ?? 0);

        if ($email === '' || $inboundId <= 0) {
            return $settings;
        }

        $freshInbound = $this->findInboundById($inboundId) ?? $inbound;
        $inboundClient = collect($freshInbound['settings']['clients'] ?? [])
            ->first(fn (mixed $client) => is_array($client) && (string) ($client['email'] ?? '') === $email);
        $clientListEntry = collect($this->getClientListEntries())
            ->first(function (mixed $row) use ($email, $inboundId): bool {
                if (! is_array($row) || (string) ($row['email'] ?? '') !== $email) {
                    return false;
                }

                $inboundIds = is_array($row['inboundIds'] ?? null) ? $row['inboundIds'] : [];

                return in_array($inboundId, array_map(fn (mixed $value) => (int) $value, $inboundIds), true);
            });

        return [
            ...$settings,
            ...(is_array($inboundClient) ? $inboundClient : []),
            ...(is_array($clientListEntry) ? $clientListEntry : []),
        ];
    }

    /**
     * @param  array<string, mixed>  $inbound
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function resolveLocalConfigLookupAttributes(array $inbound, array $attributes): array
    {
        if (! $this->isWireGuardInbound($inbound) && filled($attributes['uuid'] ?? null)) {
            return [
                'server_id' => $this->server->id,
                'uuid' => $attributes['uuid'],
            ];
        }

        return [
            'server_id' => $this->server->id,
            'inbound_id' => $attributes['inbound_id'] ?? null,
            'name' => $attributes['name'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findInboundById(int $inboundId): ?array
    {
        return collect($this->getInbounds())
            ->map(fn (array $row) => $this->normalizeInbound($row))
            ->first(fn (array $row) => (int) ($row['id'] ?? 0) === $inboundId);
    }

    /**
     * @param  array<string, mixed>  $inbound
     */
    private function isWireGuardInbound(array $inbound): bool
    {
        return mb_strtolower((string) ($inbound['protocol'] ?? '')) === 'wireguard';
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolveWireGuardIdentifier(array $settings, string $fallback): string
    {
        return (string) ($this->firstNonEmptyString([
            $settings['id'] ?? null,
            $settings['uuid'] ?? null,
            $settings['publicKey'] ?? null,
            $settings['public_key'] ?? null,
            $settings['email'] ?? null,
            $fallback,
        ]) ?? $fallback);
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

    protected function resolveClientForEnableState(VlessConfig $config, bool $enabled): array
    {
        if (! $this->usesTrafficEndpointForEnableState()) {
            return $this->extractClientFromInboundsPayload($config, $enabled);
        }

        try {
            $traffic = $this->getClientTraffics($config->name);

            return $this->extractClientFromTrafficPayload($traffic, $config, $enabled);
        } catch (RequestException $exception) {
            if ($exception->response?->status() !== 404) {
                throw $exception;
            }

            return $this->extractClientFromInboundsPayload($config, $enabled);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getClientListEntries(): array
    {
        $response = $this->getWithFallback($this->getClientListPaths());
        $payload = $response->json();

        return $this->normalizeClientListRows($payload);
    }

    protected function usesTrafficEndpointForEnableState(): bool
    {
        return true;
    }

    /**
     * @return array<int, string>
     */
    protected function getUpdateClientPaths(string $uuid): array
    {
        return [
            '/panel/api/inbounds/updateClient/'.$uuid,
            '/panel/inbound/updateClient/'.$uuid,
        ];
    }

    protected function updateClient(VlessConfig $config, array $client): Response
    {
        return $this->postWithFallback($this->getUpdateClientPaths($config->uuid), [
            'id' => $client['inbound_id'],
            'settings' => json_encode([
                'clients' => [$client['settings']],
            ], JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function getClientTrafficPaths(string $email): array
    {
        $encodedEmail = urlencode($email);

        return [
            '/panel/api/inbounds/getClientTraffics/'.$encodedEmail,
            '/panel/inbound/getClientTraffics/'.$encodedEmail,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function getOnlineClientPaths(): array
    {
        return [
            '/panel/api/clients/onlines',
            '/panel/clients/onlines',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function getClientListPaths(): array
    {
        return [
            '/panel/api/clients/list',
            '/panel/clients/list',
        ];
    }

    protected function extractClientFromInboundsPayload(VlessConfig $config, bool $enabled): array
    {
        return $this->extractClientFromInboundSettings(
            inboundId: (int) $config->inbound_id,
            identifier: (string) $config->uuid,
            email: (string) $config->name,
            enabled: $enabled,
            context: [
                'uuid' => $config->uuid,
                'password' => $config->password,
                'auth' => $config->auth,
                'subId' => $config->sub_id,
                'flow' => $config->flow,
            ],
        );
    }

    private function getBaseUrl(): string
    {
        return rtrim($this->server->panel_link, '/');
    }

    private function normalizeDiagnosticPath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            throw new RuntimeException('Diagnostic request path cannot be empty.');
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parsedPath = parse_url($path, PHP_URL_PATH);
            $query = parse_url($path, PHP_URL_QUERY);

            $path = is_string($parsedPath) && $parsedPath !== '' ? $parsedPath : '/';

            if (is_string($query) && $query !== '') {
                $path .= '?'.$query;
            }
        }

        return str_starts_with($path, '/') ? $path : '/'.$path;
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

    protected function getWithFallback(array $paths): Response
    {
        $lastException = null;

        foreach ($paths as $index => $path) {
            try {
                return $this->getRequest()
                    ->get($path)
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

    /**
     * @param  array<string, mixed>  $context
     * @return array{inbound_id:int, settings:array<string,mixed>}
     */
    protected function extractClientFromInboundSettings(
        int $inboundId,
        string $identifier,
        string $email,
        bool $enabled,
        array $context = [],
    ): array {
        $inbounds = collect($this->getInbounds())
            ->map(fn (array $row) => $this->normalizeInbound($row));

        $matchedInbound = $inbounds->first(function (array $inbound) use ($inboundId): bool {
            return $inboundId <= 0 || (int) ($inbound['id'] ?? 0) === $inboundId;
        });

        if (! is_array($matchedInbound)) {
            throw new RuntimeException("Inbound [{$inboundId}] was not found for server [{$this->server->id}]");
        }

        $clients = $matchedInbound['settings']['clients'] ?? [];

        if (! is_array($clients)) {
            throw new RuntimeException("Inbound [{$inboundId}] does not contain clients for server [{$this->server->id}]");
        }

        $matchedClient = collect($clients)->first(function (mixed $client) use ($identifier, $email) {
            if (! is_array($client)) {
                return false;
            }

            return (string) ($client['id'] ?? $client['password'] ?? '') === $identifier
                || (string) ($client['email'] ?? '') === $email;
        });

        if (! is_array($matchedClient)) {
            throw new RuntimeException("Client [{$email}] was not found in inbound settings for server [{$this->server->id}]");
        }

        return [
            'inbound_id' => (int) ($matchedInbound['id'] ?? $inboundId),
            'settings' => array_filter(array_merge(
                $this->getDefaultClientSettings(),
                $matchedClient,
                $context,
                [
                    'id' => $context['uuid'] ?? $matchedClient['id'] ?? null,
                    'email' => $matchedClient['email'] ?? $email,
                    'password' => $context['password'] ?? $matchedClient['password'] ?? null,
                    'auth' => $context['auth'] ?? $matchedClient['auth'] ?? null,
                    'subId' => $context['subId'] ?? $matchedClient['subId'] ?? null,
                    'flow' => $context['flow'] ?? $matchedClient['flow'] ?? null,
                    'method' => $context['method'] ?? $matchedClient['method'] ?? null,
                    'enable' => $enabled,
                ],
            ), fn (mixed $value) => $value !== null),
        ];
    }

    protected function postUpdateClientByIdentifier(string $identifier, array $client): Response
    {
        return $this->postWithFallback($this->getUpdateClientPaths($identifier), [
            'id' => $client['inbound_id'],
            'settings' => json_encode([
                'clients' => [$client['settings']],
            ], JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeClientStats(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, is_array(...)));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values(array_filter($decoded, is_array(...))) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeClientListRows(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $rows = $payload['obj']['items'] ?? $payload['data']['items'] ?? $payload['obj'] ?? $payload['data'] ?? [];

        return is_array($rows) ? array_values(array_filter($rows, is_array(...))) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeOnlineEntry(mixed $row): array
    {
        if (is_string($row)) {
            return [[
                'email' => $row,
                'ip' => '',
                'first_seen' => now(),
                'last_seen' => now(),
            ]];
        }

        if (! is_array($row)) {
            return [];
        }

        $email = (string) ($row['email'] ?? $row['remark'] ?? $row['clientEmail'] ?? '');
        $ips = $row['ips'] ?? $row['ip'] ?? $row['rawIp'] ?? [];

        if (is_string($ips)) {
            $ips = preg_split('/[\s,]+/', $ips) ?: [];
        }

        if (! is_array($ips)) {
            $ips = [];
        }

        $firstSeen = $row['firstSeen'] ?? $row['first_seen'] ?? $row['since'] ?? now();
        $lastSeen = $row['lastSeen'] ?? $row['last_seen'] ?? $row['time'] ?? now();

        return collect($ips)
            ->map(fn (mixed $ip) => trim((string) $ip))
            ->filter()
            ->map(fn (string $ip) => [
                'email' => $email,
                'ip' => $ip,
                'first_seen' => $firstSeen,
                'last_seen' => $lastSeen,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function expandOnlineEntry(mixed $row): array
    {
        if (is_string($row)) {
            return $this->normalizeOnlineIpList($row, $this->getClientIps($row));
        }

        return $this->normalizeOnlineEntry($row);
    }

    /**
     * @param  array<int, string|array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeOnlineIpList(string $email, array $rows): array
    {
        return collect($rows)
            ->map(function (mixed $row) use ($email): ?array {
                if (is_array($row)) {
                    $ip = trim((string) ($row['ip'] ?? ''));

                    if ($ip === '') {
                        return null;
                    }

                    $timestamp = trim((string) ($row['time'] ?? '')) ?: now()->toDateTimeString();

                    return [
                        'email' => $email,
                        'ip' => $ip,
                        'first_seen' => $timestamp,
                        'last_seen' => $timestamp,
                    ];
                }

                $row = trim((string) $row);

                if ($row === '') {
                    return null;
                }

                if (preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/', $row, $matches) === 1) {
                    $ip = trim($matches[1]);
                    $seenAt = trim($matches[2]);
                } else {
                    $ip = $row;
                    $seenAt = '';
                }

                if ($ip === '') {
                    return null;
                }

                $timestamp = $seenAt !== '' ? $seenAt : now()->toDateTimeString();

                return [
                    'email' => $email,
                    'ip' => $ip,
                    'first_seen' => $timestamp,
                    'last_seen' => $timestamp,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
