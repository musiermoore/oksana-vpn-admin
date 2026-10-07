<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class XrayCustomConfig extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'dns_settings_id', 'geodata_id',
        'xray_inbound_ids', 'external_subscription_config_ids', 'external_subscription_ids', 'proxy_ids',
        'xray_routing_ids', 'base_settings', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'xray_inbound_ids' => 'array',
            'external_subscription_config_ids' => 'array',
            'external_subscription_ids' => 'array',
            'proxy_ids' => 'array',
            'xray_routing_ids' => 'array',
            'base_settings' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function dnsSettings(): BelongsTo
    {
        return $this->belongsTo(XrayRoutingDnsSettings::class, 'dns_settings_id');
    }

    public function geodata(): BelongsTo
    {
        return $this->belongsTo(XrayRoutingGeodata::class, 'geodata_id');
    }

    public function outboundGroups(): HasMany
    {
        return $this->hasMany(XrayCustomConfigOutboundGroup::class)->orderBy('sort_order')->orderBy('id');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(XrayCustomConfigRoute::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
