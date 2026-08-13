<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExternalSubscriptionSourceFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VlessExternalSubscription extends Model
{
    public const TYPE_SUBSCRIPTION = 'subscription';

    public const TYPE_DIRECT = 'direct';

    protected $fillable = [
        'name',
        'sort_order',
        'description',
        'type',
        'source_format',
        'source_url',
        'filter_pattern',
        'connect_name_prefix',
        'include_in_main_subscription',
        'include_in_whitelist',
        'is_free',
        'is_active',
        'is_ready',
        'last_synced_at',
        'last_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'include_in_main_subscription' => 'boolean',
            'include_in_whitelist' => 'boolean',
            'is_free' => 'boolean',
            'is_active' => 'boolean',
            'is_ready' => 'boolean',
            'last_synced_at' => 'datetime',
            'sort_order' => 'integer',
            'source_format' => ExternalSubscriptionSourceFormat::class,
        ];
    }

    public function configs(): HasMany
    {
        return $this->hasMany(VlessExternalSubscriptionConfig::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeOrdered($query)
    {
        return $query
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

    public function isIncySourceFormat(): bool
    {
        return $this->source_format instanceof ExternalSubscriptionSourceFormat
            ? $this->source_format->isIncy()
            : (string) $this->source_format === ExternalSubscriptionSourceFormat::Incy->value;
    }
}
