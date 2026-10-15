<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XrayInboundHealthCheck extends Model
{
    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_UNHEALTHY = 'unhealthy';
    public const STATUS_UNSUPPORTED = 'unsupported';
    public const STATUS_UNKNOWN = 'unknown';

    protected $fillable = [
        'xray_inbound_id',
        'status',
        'probe_client_id',
        'probe_client_email',
        'results',
        'error',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'xray_inbound_id' => 'integer',
            'results' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function inbound(): BelongsTo
    {
        return $this->belongsTo(XrayInbound::class, 'xray_inbound_id');
    }
}
