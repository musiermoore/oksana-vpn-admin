<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class XrayRoutingDnsSettings extends Model
{
    protected $table = 'xray_routing_dns_settings';

    protected $fillable = [
        'name', 'description', 'servers', 'settings', 'query_strategy', 'enable_parallel_query', 'is_active', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'servers' => 'array',
            'settings' => 'array',
            'enable_parallel_query' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
