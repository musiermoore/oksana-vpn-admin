<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerConsoleSession extends Model
{
    protected $fillable = ['uuid', 'server_id', 'user_id', 'status', 'busy', 'expires_at'];

    protected function casts(): array
    {
        return ['busy' => 'boolean', 'expires_at' => 'datetime'];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
