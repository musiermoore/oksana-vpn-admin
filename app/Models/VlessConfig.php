<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VlessConfig extends Model
{
    private ?int $pendingInboundExternalId = null;

    protected $fillable = [
        'server_id',
        'inbound_id',
        'xray_inbound_id',
        'user_id',
        'name', // email in 3x-ui
        'description',
        'is_active',
        'enable',

        'uuid',
        'sub_id',
        'password',
        'auth',

        'port',
        'protocol',

        'type',
        'encryption',
        'security',
        'flow',

        'pbk',
        'alpn',
        'fp',
        'sni',
        'host',
        'path',
        'service_name',
        'mode',
        'obfs',
        'obfs_password',
        'extra',
        'x_padding_bytes',
        'sid',
        'spx',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'enable' => 'boolean',
            'xray_inbound_id' => 'integer',
        ];
    }

    public function setServerIdAttribute(mixed $value): void
    {
        $this->attributes['server_id'] = $value;

        $this->syncPendingInboundExternalId();
    }

    public function setInboundIdAttribute(mixed $value): void
    {
        $normalizedExternalId = (int) $value;

        if ($normalizedExternalId < 1) {
            $this->attributes['xray_inbound_id'] = null;
            $this->pendingInboundExternalId = null;

            return;
        }

        $serverId = (int) ($this->attributes['server_id'] ?? 0);

        if ($serverId < 1) {
            $this->pendingInboundExternalId = $normalizedExternalId;

            return;
        }

        $this->attributes['xray_inbound_id'] = XrayInbound::query()->firstOrCreate([
            'server_id' => $serverId,
            'external_id' => $normalizedExternalId,
        ])->getKey();

        $this->pendingInboundExternalId = null;
    }

    public function getInboundIdAttribute(): ?int
    {
        if ($this->relationLoaded('xrayInbound')) {
            return $this->xrayInbound?->external_id === null ? null : (int) $this->xrayInbound->external_id;
        }

        if ((int) ($this->xray_inbound_id ?? 0) < 1) {
            return null;
        }

        $externalId = $this->xrayInbound()->value('external_id');

        return $externalId === null ? null : (int) $externalId;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)
            ->withTrashed();
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function xrayInbound(): BelongsTo
    {
        return $this->belongsTo(XrayInbound::class);
    }

    public function getResolvedInboundId(): ?int
    {
        return $this->inbound_id;
    }

    public function getLinkAttribute(): string
    {
        return $this->getLink();
    }

    public function getLink(): string
    {
        if ($this->getNormalizedProtocol() === 'wireguard') {
            return $this->getStaticLink();
        }

        if (empty($this->sub_id)) {
            return $this->getStaticLink();
        }

        return $this->getSubscriptionLink();
    }

    public function getStaticLink(): string
    {
        return match ($this->getNormalizedProtocol()) {
            'wireguard' => $this->getWireGuardStaticLink(),
            'trojan' => $this->getTrojanStaticLink(),
            'hysteria', 'hy' => $this->shouldBuildHysteria2Link()
                ? $this->getHysteria2StaticLink()
                : $this->getHysteriaStaticLink(),
            'hysteria2', 'hy2' => $this->getHysteria2StaticLink(),
            default => $this->getVlessStaticLink(),
        };
    }

    private function getVlessStaticLink(): string
    {
        $paramList = [
            "type={$this->type}",
            "encryption={$this->encryption}",
            "security={$this->security}",
        ];

        if ($this->security === 'reality') {
            $paramList[] = "pbk={$this->pbk}";
            $paramList[] = "fp={$this->fp}";
            $paramList[] = "sni={$this->sni}";
            $paramList[] = "sid={$this->sid}";
            $paramList[] = 'spx='.urlencode($this->spx ?: '/');
        } else {
            if ($this->security && $this->sni) {
                $paramList[] = "sni={$this->sni}";
            }

            if ($this->type === 'ws') {
                if ($this->host) {
                    $paramList[] = 'host='.urlencode($this->host);
                }

                if ($this->path) {
                    $paramList[] = 'path='.urlencode($this->path);
                }
            }

            if ($this->type === 'grpc' && $this->service_name) {
                $paramList[] = 'serviceName='.urlencode($this->service_name);
            }
        }

        if (in_array($this->type, ['http', 'h2', 'xhttp'], true)) {
            if ($this->type === 'xhttp' || $this->host) {
                $paramList[] = 'host='.urlencode((string) $this->host);
            }

            if ($this->path) {
                $paramList[] = 'path='.urlencode($this->path);
            }
        }

        if ($this->type === 'xhttp') {
            if ($this->mode) {
                $paramList[] = 'mode='.urlencode($this->mode);
            }

            if ($this->extra) {
                $paramList[] = 'extra='.urlencode($this->extra);
            }

            if ($this->x_padding_bytes !== null && $this->x_padding_bytes !== '') {
                $paramList[] = 'x_padding_bytes='.urlencode((string) $this->x_padding_bytes);
            }
        }

        if ($this->flow) {
            $paramList[] = "flow={$this->flow}";
        }

        $params = implode('&', $paramList);

        $label = str($this->server->code.'_'.$this->name)->slug();

        return "vless://{$this->uuid}@{$this->server->getLinkAddressHost()}:{$this->port}?{$params}#{$label}";
    }

    private function getWireGuardStaticLink(): string
    {
        $link = trim((string) $this->extra);

        return str_starts_with($link, 'wireguard://') ? $link : '';
    }

    private function getTrojanStaticLink(): string
    {
        $paramList = [
            "security={$this->security}",
            "type={$this->type}",
        ];

        if ($this->security && $this->sni) {
            $paramList[] = "sni={$this->sni}";
        }

        if ($this->type === 'ws') {
            if ($this->host) {
                $paramList[] = 'host='.urlencode($this->host);
            }

            if ($this->path) {
                $paramList[] = 'path='.urlencode($this->path);
            }
        }

        if ($this->type === 'grpc' && $this->service_name) {
            $paramList[] = 'serviceName='.urlencode($this->service_name);
        }

        $params = implode('&', $paramList);
        $label = str($this->server->code.'_'.$this->name)->slug();
        $password = $this->password ?: $this->uuid;

        return "trojan://{$password}@{$this->server->getLinkAddressHost()}:{$this->port}?{$params}#{$label}";
    }

    private function getHysteriaStaticLink(): string
    {
        $paramList = array_filter([
            'protocol='.($this->type ?: 'udp'),
            'auth='.urlencode((string) ($this->auth ?: $this->password ?: $this->uuid)),
            $this->sni ? 'peer='.urlencode($this->sni) : null,
            $this->security === 'none' ? 'insecure=1' : null,
        ]);

        $params = implode('&', $paramList);
        $label = str($this->server->code.'_'.$this->name)->slug();

        return "hysteria://{$this->server->getLinkAddressHost()}:{$this->port}?{$params}#{$label}";
    }

    private function getHysteria2StaticLink(): string
    {
        $secret = rawurlencode((string) ($this->auth ?: $this->password ?: $this->uuid));
        $paramList = array_filter([
            $this->alpn ? 'alpn='.urlencode($this->alpn) : null,
            $this->buildHysteria2FastOpenMetadata(),
            $this->fp ? 'fp='.urlencode($this->fp) : null,
            $this->obfs ? 'obfs='.urlencode($this->obfs) : null,
            $this->obfs_password ? 'obfs-password='.urlencode($this->obfs_password) : null,
            $this->security ? 'security='.urlencode($this->security) : null,
            $this->sni ? 'sni='.urlencode($this->sni) : null,
            $this->security === 'none' ? 'insecure=1' : null,
        ]);
        $params = implode('&', $paramList);
        $label = str($this->server->code.'_'.$this->name)->slug();
        $query = $params !== '' ? "?{$params}" : '';

        return "hysteria2://{$secret}@{$this->server->getLinkAddressHost()}:{$this->port}{$query}#{$label}";
    }

    private function getNormalizedProtocol(): string
    {
        return mb_strtolower((string) ($this->protocol ?: 'vless'));
    }

    private function shouldBuildHysteria2Link(): bool
    {
        return $this->alpn !== null
            || $this->obfs !== null
            || $this->obfs_password !== null
            || $this->security !== null && mb_strtolower($this->security) === 'tls';
    }

    private function buildHysteria2FastOpenMetadata(): ?string
    {
        if (mb_strtolower((string) $this->obfs) !== 'salamander' || empty($this->obfs_password)) {
            return null;
        }

        $payload = json_encode([
            'udp' => [[
                'settings' => [
                    'password' => $this->obfs_password,
                ],
                'type' => 'salamander',
            ]],
        ], JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return null;
        }

        return 'fm='.urlencode($payload);
    }

    public function getBaseUrl(): string
    {
        return "{$this->server->getScheme()}://{$this->server->getHost()}";
    }

    public function getSubscriptionLink(): string
    {
        return "{$this->getBaseUrl()}/sub/{$this->sub_id}";
    }

    public function getQrCodeContent(): string
    {
        return $this->getLink();
    }

    private function syncPendingInboundExternalId(): void
    {
        if ($this->pendingInboundExternalId === null) {
            return;
        }

        $this->setInboundIdAttribute($this->pendingInboundExternalId);
    }
}
