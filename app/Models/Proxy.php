<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Proxy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'host',
        'port',
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
        ];
    }

    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class)
            ->withTimestamps();
    }
}
