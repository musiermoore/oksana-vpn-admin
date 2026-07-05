<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'login',
        'password',
        'service_name',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
        ];
    }
}
