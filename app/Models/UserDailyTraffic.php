<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDailyTraffic extends Model
{
    protected $fillable = [
        'user_id',
        'traffic_date',
        'upload_bytes',
        'download_bytes',
    ];

    protected function casts(): array
    {
        return [
            'traffic_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
