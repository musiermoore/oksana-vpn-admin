<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionType extends Model
{
    use HasFactory;

    public const SLUG_DEPOSIT = 'deposit';
    public const SLUG_SUBSCRIPTION = 'subscription';
    public const SLUG_EXTRA_PAYMENT = 'extra-payment';

    protected $fillable = [
        'name',
        'slug',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'type_id');
    }

    public static function idBySlug(string $slug): ?int
    {
        return self::query()
            ->where('slug', $slug)
            ->value('id');
    }
}
