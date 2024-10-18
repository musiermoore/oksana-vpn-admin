<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Limit extends Model
{
    use HasFactory;

    protected $fillable = [
        'config_id',
        'amount',
        'start_date',
        'end_date',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(Config::class);
    }

    public static function getSpeedLimits(): array
    {
        return [
            [
                'name' => '30 Мбит',
                'amount' => 30,
                'priority' => 30,
            ],
            [
                'name' => '10 Мбит',
                'amount' => 10,
                'priority' => 10,
            ],
        ];
    }
}
