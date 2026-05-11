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
        'current_balance_amount',
        'is_approved',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'current_balance_amount' => 'float',
            'is_approved' => 'bool',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Transaction $transaction) {
            if ($transaction->is_approved) {
                self::applyBalanceDelta(
                    userId: (int) $transaction->user_id,
                    amount: (float) $transaction->amount,
                );
                self::syncCurrentBalanceAmounts((int) $transaction->user_id);
            }
        });

        static::updated(function (Transaction $transaction) {
            if (! $transaction->wasChanged(['user_id', 'amount', 'is_approved'])) {
                return;
            }

            $originalUserId = (int) $transaction->getOriginal('user_id');
            $currentUserId = (int) $transaction->user_id;
            $originalAmount = (float) $transaction->getOriginal('amount');
            $currentAmount = (float) $transaction->amount;
            $wasApproved = (bool) $transaction->getOriginal('is_approved');
            $isApproved = (bool) $transaction->is_approved;

            if ($wasApproved) {
                self::applyBalanceDelta(
                    userId: $originalUserId,
                    amount: -$originalAmount,
                );
            }

            if ($isApproved) {
                self::applyBalanceDelta(
                    userId: $currentUserId,
                    amount: $currentAmount,
                );
            }

            $affectedUserIds = array_unique(array_filter([
                $wasApproved ? $originalUserId : null,
                $isApproved ? $currentUserId : null,
            ]));

            foreach ($affectedUserIds as $affectedUserId) {
                self::syncCurrentBalanceAmounts((int) $affectedUserId);
            }
        });

        static::deleted(function (Transaction $transaction) {
            if ($transaction->is_approved) {
                self::applyBalanceDelta(
                    userId: (int) $transaction->user_id,
                    amount: -(float) $transaction->amount,
                );
                self::syncCurrentBalanceAmounts((int) $transaction->user_id);
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

    public function getApprovalMessageTextAttribute(): string
    {
        $amount = abs((float) $this->amount);

        if ((float) $this->amount < 0) {
            return "С баланса было списано $amount";
        }

        return "Баланс был пополнен на $amount";
    }

    private static function applyBalanceDelta(int $userId, float $amount): void
    {
        if ($userId <= 0 || $amount === 0.0) {
            return;
        }

        User::query()
            ->whereKey($userId)
            ->increment('balance', $amount);
    }

    private static function syncCurrentBalanceAmounts(int $userId): void
    {
        $approvedTransactions = self::query()
            ->where('user_id', $userId)
            ->where('is_approved', true)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'amount']);

        $runningBalance = 0.0;

        foreach ($approvedTransactions as $approvedTransaction) {
            $runningBalance += (float) $approvedTransaction->amount;

            self::query()
                ->whereKey($approvedTransaction->id)
                ->update(['current_balance_amount' => $runningBalance]);
        }

        self::query()
            ->where('user_id', $userId)
            ->where('is_approved', false)
            ->update(['current_balance_amount' => null]);
    }
}
