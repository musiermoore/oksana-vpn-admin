<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActiveConnection extends Model
{
    public const CONFIG_TYPE_VLESS = 'vless';

    public const CONFIG_TYPE_SHADOWSOCKS = 'shadowsocks';

    protected $fillable = [
        'user_id',
        'server_id',
        'config_type',
        'config_id',
        'protocol',
        'ip',
        'first_seen',
        'last_seen',
    ];

    protected function casts(): array
    {
        return [
            'first_seen' => 'datetime',
            'last_seen' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function scopeActive(Builder $query, ?\DateTimeInterface $threshold = null): Builder
    {
        return $query->where('last_seen', '>', $threshold ?? now()->subMinutes(2));
    }

    public function scopeStale(Builder $query, ?\DateTimeInterface $threshold = null): Builder
    {
        return $query->where('last_seen', '<=', $threshold ?? now()->subDay());
    }
}
