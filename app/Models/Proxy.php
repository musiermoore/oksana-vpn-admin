<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Proxy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'host',
        'port',
        'xray_inbound_id',
        'is_https',
        'is_ready',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_https' => 'boolean',
            'is_ready' => 'boolean',
            'port' => 'integer',
            'xray_inbound_id' => 'integer',
        ];
    }

    public function xrayInbound(): BelongsTo
    {
        return $this->belongsTo(XrayInbound::class);
    }

    public function getInboundIdAttribute(): ?int
    {
        if ($this->relationLoaded('xrayInbound')) {
            return $this->xrayInbound?->external_id === null ? null : (int) $this->xrayInbound->external_id;
        }

        if ((int) ($this->xray_inbound_id ?? 0) < 1) {
            return null;
        }

        $externalId = $this->xrayInbound()->value('external_id');

        return $externalId === null ? null : (int) $externalId;
    }

    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class)
            ->withTimestamps();
    }
}
