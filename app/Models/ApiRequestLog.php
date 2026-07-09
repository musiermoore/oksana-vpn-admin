<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequestLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'method',
        'endpoint',
        'params',
        'request_timezone',
        'request_timezone_offset',
        'response_status',
        'ip_address',
        'forwarded_for',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'request_timezone_offset' => 'integer',
            'response_status' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)
            ->withTrashed();
    }
}
