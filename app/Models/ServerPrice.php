<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerPrice extends Model
{
    protected $fillable = [
        'server_id',
        'effective_from',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'server_id' => 'integer',
            'effective_from' => 'date',
            'price' => 'float',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
