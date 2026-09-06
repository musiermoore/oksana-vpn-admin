<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VlessExternalSubscriptionConfig extends Model
{
    protected $fillable = [
        'vless_external_subscription_id',
        'config_key',
        'name',
        'normalized_name',
        'protocol',
        'url',
        'json',
        'sort_order',
    ];

    protected $casts = [
        'json' => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(VlessExternalSubscription::class, 'vless_external_subscription_id');
    }
}
