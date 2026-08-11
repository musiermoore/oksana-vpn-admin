<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiveawaySeries extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'auto_repeat_enabled',
        'repeat_delay_minutes',
        'repeat_limit',
    ];

    protected function casts(): array
    {
        return [
            'auto_repeat_enabled' => 'boolean',
            'repeat_delay_minutes' => 'integer',
            'repeat_limit' => 'integer',
        ];
    }

    public function giveaways(): HasMany
    {
        return $this->hasMany(Giveaway::class, 'series_id');
    }
}
