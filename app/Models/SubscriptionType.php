<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SubscriptionType extends Model
{
    public const KIND_CONNECT = 'connect';

    public const KIND_CONNECT_V2 = 'connect_v2';

    public const KIND_CONNECT_FREE = 'connect_free';

    public const FORMAT_URI = 'uri';

    public const FORMAT_CLASH = 'clash';

    public const FORMAT_SING_BOX = 'sing-box';

    public const FORMAT_JSON = 'json';

    public const KINDS = [
        self::KIND_CONNECT,
        self::KIND_CONNECT_V2,
        self::KIND_CONNECT_FREE,
    ];

    public const FORMATS = [
        self::FORMAT_URI,
        self::FORMAT_CLASH,
        self::FORMAT_SING_BOX,
        self::FORMAT_JSON,
    ];

    protected $fillable = [
        'name',
        'slug',
        'kind',
        'format',
        'is_active',
        'show_expired_placeholder',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_expired_placeholder' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionTypeItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name')->orderBy('id');
    }
}
