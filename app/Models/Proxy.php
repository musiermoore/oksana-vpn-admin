<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proxy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'proxy_group_id',
        'server_id',
        'sort_order',
        'xray_inbound_id',
        'hide_main_node_name',
        'is_https',
        'is_ready',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_https' => 'boolean',
            'is_ready' => 'boolean',
            'server_id' => 'integer',
            'proxy_group_id' => 'integer',
            'sort_order' => 'integer',
            'xray_inbound_id' => 'integer',
            'hide_main_node_name' => 'boolean',
        ];
    }

    public function resolveConnectNodeServerName(string $mainNodeServerName): string
    {
        if ((bool) $this->hide_main_node_name) {
            return (string) $this->name;
        }

        return sprintf('%s (%s)', $mainNodeServerName, $this->name);
    }

    public function xrayInbound(): BelongsTo
    {
        return $this->belongsTo(XrayInbound::class);
    }

    public function hosts(): HasMany
    {
        return $this->hasMany(ProxyHost::class)->orderBy('sort')->orderBy('id');
    }

    public function entryHost(): ?ProxyHost
    {
        if ($this->relationLoaded('hosts')) {
            return $this->hosts->first(fn (ProxyHost $host): bool => $host->is_active);
        }

        return $this->hosts()->where('is_active', true)->first();
    }

    public function getHostAttribute(): ?string
    {
        return $this->entryHost()?->host;
    }

    public function getPortAttribute(): ?int
    {
        $port = $this->entryHost()?->port;

        return $port === null ? null : (int) $port;
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

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function proxyGroup(): BelongsTo
    {
        return $this->belongsTo(ProxyGroup::class);
    }
}
