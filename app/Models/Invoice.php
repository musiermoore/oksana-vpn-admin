<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_payment_id',
        'status',
        'paid',
        'amount',
        'currency',
        'description',
        'confirmation_url',
        'payload',
        'history',
        'paid_at',
        'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'paid' => 'bool',
            'amount' => 'float',
            'payload' => 'array',
            'history' => 'array',
            'paid_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)
            ->withTrashed();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
