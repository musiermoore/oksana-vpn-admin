<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NonRegularPayment extends Model
{
    protected $fillable = [
        'amount',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
        ];
    }
}
