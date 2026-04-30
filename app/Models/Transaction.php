<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'is_approved'
    ];

    protected static function booted(): void
    {
        static::created(function (Transaction $transaction) {
            if ($transaction->is_approved) {
                self::syncUserBalance($transaction->user_id);
            }
        });

        static::updated(function (Transaction $transaction) {
            $affectedUserIds = collect([
                $transaction->user_id,
                $transaction->getOriginal('user_id'),
            ])->filter()->unique();

            if (
                $transaction->wasChanged(['user_id', 'amount', 'is_approved'])
                || $transaction->getOriginal('is_approved')
            ) {
                foreach ($affectedUserIds as $userId) {
                    self::syncUserBalance((int) $userId);
                }
            }
        });

        static::deleted(function (Transaction $transaction) {
            self::syncUserBalance((int) $transaction->user_id);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class)
            ->withTrashed();
    }

    public function getFormattedCreatedAtAttribute()
    {
        return Carbon::make($this->attributes['created_at'])->format('d.m.Y H:i');
    }

    public static function syncUserBalance(int $userId): void
    {
        $user = User::query()->find($userId);

        if (! $user) {
            return;
        }

        $user->syncStoredBalance();
    }
}
