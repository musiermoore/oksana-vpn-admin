<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proxy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'host',
        'port',
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
            'port' => 'integer',
            'server_id' => 'integer',
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
}
