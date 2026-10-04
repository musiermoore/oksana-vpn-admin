<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class XrayRoutingGeodata extends Model
{
    protected $table = 'xray_routing_geodata';

    protected $fillable = [
        'name', 'description', 'geoip_url', 'geosite_url', 'assets', 'last_updated', 'is_active',
    ];

    protected function casts(): array
    {
        return ['assets' => 'array', 'is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
