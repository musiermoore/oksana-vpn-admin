<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class XrayCustomConfigUser extends Model
{
    protected $table = 'xray_custom_config_user';

    protected $fillable = [
        'xray_custom_config_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'xray_custom_config_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    public function xrayCustomConfig(): BelongsTo
    {
        return $this->belongsTo(XrayCustomConfig::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(XrayCustomConfigUserPrice::class)->orderByDesc('effective_from');
    }

    public function currentPrice(): HasOne
    {
        return $this->hasMany(XrayCustomConfigUserPrice::class)
            ->latestOfMany('effective_from');
    }
}
