<?php

namespace App\Models;

use App\Events\UserBalanceDeltaRequested;
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
                event(new UserBalanceDeltaRequested(
                    userId: (int) $transaction->user_id,
                    amount: (float) $transaction->amount,
                ));
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
                event(new UserBalanceDeltaRequested(
                    userId: $originalUserId,
                    amount: -$originalAmount,
                ));
            }

            if ($isApproved) {
                event(new UserBalanceDeltaRequested(
                    userId: $currentUserId,
                    amount: $currentAmount,
                ));
            }
        });

        static::deleted(function (Transaction $transaction) {
            if ($transaction->is_approved) {
                event(new UserBalanceDeltaRequested(
                    userId: (int) $transaction->user_id,
                    amount: -(float) $transaction->amount,
                ));
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
}
