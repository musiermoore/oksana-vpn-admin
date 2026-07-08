<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VlessExternalSubscription extends Model
{
    public const TYPE_SUBSCRIPTION = 'subscription';

    public const TYPE_DIRECT = 'direct';

    protected $fillable = [
        'name',
        'description',
        'type',
        'source_url',
        'filter_pattern',
        'connect_name_prefix',
        'is_active',
        'is_ready',
        'last_synced_at',
        'last_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_ready' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function configs(): HasMany
    {
        return $this->hasMany(VlessExternalSubscriptionConfig::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeVisibleForUser($query, User $user)
    {
        return $user->is_admin
            ? $query
            : $query->where('is_ready', true);
    }

    public function isSubscriptionType(): bool
    {
        return $this->type === self::TYPE_SUBSCRIPTION;
    }

    public function isDirectType(): bool
    {
        return $this->type === self::TYPE_DIRECT;
    }
}
