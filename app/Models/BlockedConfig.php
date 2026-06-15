<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedConfig extends Model
{
    protected $fillable = [
        'user_id',
        'server_id',
        'config_type',
        'config_id',
        'reason',
        'blocked_until',
    ];

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'blocked_until' => 'datetime',
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
}
