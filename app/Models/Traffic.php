<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Traffic extends Model
{
    use HasFactory;

    protected $fillable = [
        'config_id',
        'sent',
        'received',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(Config::class);
    }
}
