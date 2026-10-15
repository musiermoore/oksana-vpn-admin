<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class XrayInbound extends Model
{
    protected $fillable = [
        'server_id',
        'external_id',
        'sort_order',
        'is_active',
        'is_public',
        'is_visible_in_connect',
        'is_visible_in_connect_v2',
        'params',
    ];

    protected function casts(): array
    {
        return [
            'server_id' => 'integer',
            'external_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_visible_in_connect' => 'boolean',
            'is_visible_in_connect_v2' => 'boolean',
            'params' => 'array',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function vlessConfigs(): HasMany
    {
        return $this->hasMany(VlessConfig::class);
    }

    public function healthCheck(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(XrayInboundHealthCheck::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('external_id')
            ->orderBy('id');
    }
}
