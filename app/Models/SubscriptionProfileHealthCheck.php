<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SubscriptionProfileHealthCheck extends Model
{
    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_UNHEALTHY = 'unhealthy';
    public const STATUS_UNSUPPORTED = 'unsupported';
    public const STATUS_UNKNOWN = 'unknown';
    public const STATUS_PENDING = 'pending';

    protected $fillable = [
        'flow',
        'profile_key',
        'name',
        'status',
        'results',
        'error',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'results' => 'array',
            'checked_at' => 'datetime',
        ];
    }
}
