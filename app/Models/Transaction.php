<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type_id',
        'amount',
        'is_approved',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'is_approved' => 'bool',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Transaction $transaction) {
            if ($transaction->is_approved) {
                self::applyBalanceDelta((int) $transaction->user_id, (float) $transaction->amount);
            }
        });

        static::updated(function (Transaction $transaction) {
            $originalUserId = (int) $transaction->getOriginal('user_id');
            $currentUserId = (int) $transaction->user_id;
            $originalAmount = (float) $transaction->getOriginal('amount');
            $currentAmount = (float) $transaction->amount;
            $wasApproved = (bool) $transaction->getOriginal('is_approved');
            $isApproved = (bool) $transaction->is_approved;

            if ($wasApproved) {
                self::applyBalanceDelta($originalUserId, -$originalAmount);
            }

            if ($isApproved) {
                self::applyBalanceDelta($currentUserId, $currentAmount);
            }
        });

        static::deleted(function (Transaction $transaction) {
            if ($transaction->is_approved) {
                self::applyBalanceDelta((int) $transaction->user_id, -(float) $transaction->amount);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)
            ->withTrashed();
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class, 'type_id');
    }

    public function getFormattedCreatedAtAttribute()
    {
        return Carbon::make($this->attributes['created_at'])->format('d.m.Y H:i');
    }

    public static function applyBalanceDelta(int $userId, float $amount): void
    {
        if ($userId <= 0 || $amount == 0.0) {
            return;
        }

        User::query()
            ->whereKey($userId)
            ->increment('balance', $amount);
    }
}
