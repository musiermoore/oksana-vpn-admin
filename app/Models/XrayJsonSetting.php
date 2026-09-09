<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class XrayJsonSetting extends Model
{
    protected $fillable = [
        'name',
        'description',
        'source',
        'dns',
        'routing',
        'geodata',
        'raw',
        'is_active',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'dns' => 'array',
            'routing' => 'array',
            'geodata' => 'array',
            'raw' => 'array',
            'is_active' => 'boolean',
            'imported_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLatestActive(Builder $query): Builder
    {
        return $query
            ->active()
            ->latest('id');
    }
}
