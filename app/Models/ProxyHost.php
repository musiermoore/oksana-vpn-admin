<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProxyHost extends Model
{
    protected $fillable = ['host', 'port', 'sort', 'is_active'];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function proxy(): BelongsTo
    {
        return $this->belongsTo(Proxy::class);
    }
}
