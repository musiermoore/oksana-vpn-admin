<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class ConfigGroup extends Model
{
    protected $fillable = ['name', 'sort_order'];

    public function vlessConfigs(): MorphToMany
    {
        return $this->morphedByMany(VlessConfig::class, 'groupable', 'config_group_items');
    }

    public function externalSubscriptions(): MorphToMany
    {
        return $this->morphedByMany(VlessExternalSubscription::class, 'groupable', 'config_group_items');
    }

    public function xrayCustomConfigs(): MorphToMany
    {
        return $this->morphedByMany(XrayCustomConfig::class, 'groupable', 'config_group_items');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
