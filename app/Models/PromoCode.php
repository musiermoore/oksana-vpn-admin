<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PromoCodeBenefit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromoCode extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'benefit', 'value', 'once_per_user', 'new_users_only', 'is_active', 'starts_on', 'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'benefit' => PromoCodeBenefit::class,
            'value' => 'float',
            'once_per_user' => 'boolean',
            'new_users_only' => 'boolean',
            'is_active' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoCodeRedemption::class);
    }

    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }
}
