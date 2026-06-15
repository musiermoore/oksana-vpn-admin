<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserServerStatHistory extends Model
{
    protected $fillable = [
        'user_id',
        'server_id',
        'upload_bytes',
        'download_bytes',
        'collected_at',
    ];

    protected function casts(): array
    {
        return [
            'collected_at' => 'datetime',
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
