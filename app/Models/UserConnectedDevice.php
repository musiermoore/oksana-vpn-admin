<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserConnectedDevice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'label',
        'device',
        'user_agent',
        'user_agent_hash',
        'ip_address',
        'first_connection_at',
        'last_connection_at',
        'connection_count',
        'connection_route',
    ];

    protected function casts(): array
    {
        return [
            'first_connection_at' => 'datetime',
            'last_connection_at' => 'datetime',
            'connection_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)
            ->withTrashed();
    }
}
