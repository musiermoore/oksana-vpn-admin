<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class XrayInbound extends Model
{
    protected $fillable = [
        'server_id',
        'external_id',
        'is_active',
        'is_public',
        'params',
    ];

    protected function casts(): array
    {
        return [
            'server_id' => 'integer',
            'external_id' => 'integer',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'params' => 'array',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function vlessConfigs(): HasMany
    {
        return $this->hasMany(VlessConfig::class);
    }

}
