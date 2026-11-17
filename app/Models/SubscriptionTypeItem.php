<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubscriptionTypeItem extends Model
{
    public const SOURCE_SERVER = 'server';

    public const SOURCE_PROXY = 'proxy';

    public const SOURCE_EXTERNAL_SUBSCRIPTION = 'external_subscription';

    public const SOURCE_XRAY_CUSTOM_CONFIG = 'xray_custom_config';

    public const SOURCES = [
        self::SOURCE_SERVER,
        self::SOURCE_PROXY,
        self::SOURCE_EXTERNAL_SUBSCRIPTION,
        self::SOURCE_XRAY_CUSTOM_CONFIG,
    ];

    protected $fillable = [
        'source_type',
        'source_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'source_id' => 'integer'];
    }

    public function subscriptionType(): BelongsTo
    {
        return $this->belongsTo(SubscriptionType::class);
    }
}
