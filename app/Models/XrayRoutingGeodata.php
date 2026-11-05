<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function customConfigs(): HasMany
    {
        return $this->hasMany(XrayCustomConfig::class, 'geodata_id');
    }

    public function externalSubscriptions(): HasMany
    {
        return $this->hasMany(VlessExternalSubscription::class, 'geodata_id');
    }

    public function clientMappings(): HasMany
    {
        return $this->hasMany(XrayCustomConfigGeodata::class, 'geodata_id');
    }
}
