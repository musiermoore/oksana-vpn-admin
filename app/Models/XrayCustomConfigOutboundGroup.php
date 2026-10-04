<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\XrayBalancerStrategy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XrayCustomConfigOutboundGroup extends Model
{
    protected $fillable = [
        'xray_custom_config_id', 'name', 'tag', 'strategy', 'fallback_group_id',
        'xray_inbound_ids', 'external_subscription_config_ids', 'proxy_ids',
        'strategy_settings', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'strategy' => XrayBalancerStrategy::class,
            'xray_inbound_ids' => 'array',
            'external_subscription_config_ids' => 'array',
            'proxy_ids' => 'array',
            'strategy_settings' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(XrayCustomConfig::class, 'xray_custom_config_id');
    }

    public function fallbackGroup(): BelongsTo
    {
        return $this->belongsTo(self::class, 'fallback_group_id');
    }
}
