<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'telegram',
        'telegram_id',
        'description',
        'join_at',
        'balance',
        'is_admin',
        'is_active'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'balance' => 'float',
        ];
    }

    public function configs()
    {
        return $this->hasMany(Config::class);
    }

    public function vlessConfigs()
    {
        return $this->hasMany(VlessConfig::class);
    }

    public function tokens()
    {
        return $this->hasMany(UserToken::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function approvedTransactions()
    {
        return $this->transactions()
            ->where('transactions.is_approved', '=', true);
    }

    public function extraPayments(): HasMany
    {
        return $this->hasMany(UserExtraPayment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->ofMany('end_date', 'max');
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class)
            ->ofMany('end_date', 'max');
    }

    public function getFullNameAttribute(): string
    {
        return $this->attributes['telegram']
            . ' (' . $this->attributes['name'] . ')'
            . ($this->is_active ? '' : ' - Удалён');
    }

    public function getIsActiveAttribute(): bool
    {
        return empty($this->attributes['deleted_at']);
    }

    public function createConfig(array $config): bool
    {
        DB::beginTransaction();

        try {
            $config = $this->configs()->create($config);
            $isCreated = $config->createWgConfig();

            if ($isCreated) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            return $isCreated;
        } catch (Exception $exception) {
            DB::rollBack();

            return false;
        }
    }

    public function hasDebt()
    {
        return $this->getDebtAmount() > 0;
    }

    public function hasActiveAccess(): bool
    {
        return $this->hasActiveSubscription() && ! $this->hasDebt();
    }

    public function hasActiveSubscription(): bool
    {
        if ($this->relationLoaded('activeSubscription')) {
            return $this->activeSubscription !== null;
        }

        return $this->activeSubscription()->exists();
    }

    public function getBalanceAmount(string $transactionsRelation = 'transactions'): float
    {
        return $this->getStoredBalanceAmount();
    }

    public function getDebtAmount(string $transactionsRelation = 'transactions'): float
    {
        return max(0, -$this->getStoredBalanceAmount());
    }

    public function getPaymentAmount(): float
    {
        if (array_key_exists('payment_amount', $this->attributes)) {
            return (float) $this->attributes['payment_amount'];
        }

        $user = self::query()
            ->select('users.id')
            ->whereKey($this->getKey())
            ->tap(fn (Builder $query) => self::applyBillingSummary($query))
            ->first();

        return (float) ($user?->payment_amount ?? 0);
    }

    public static function applyBillingSummary(
        Builder $query,
        string $transactionsRelation = 'approvedTransactions'
    ): Builder {
        return $query->addSelect([
            'payment_amount' => Transaction::query()
                ->selectRaw('ABS(COALESCE(SUM(amount), 0))')
                ->whereColumn('transactions.user_id', 'users.id')
                ->where('transactions.is_approved', true)
                ->where('transactions.amount', '<', 0),
        ]);
    }

    public function getStoredBalanceAmount(): float
    {
        if (array_key_exists('balance', $this->attributes)) {
            return (float) $this->attributes['balance'];
        }

        $user = self::query()
            ->select(['users.id', 'users.balance'])
            ->whereKey($this->getKey())
            ->first();

        return (float) ($user?->balance ?? 0);
    }

    public function createDefaultConfigs(): bool
    {
        $success = true;

        $servers = Server::all();

        $configs = $servers->map(fn($server) => [
            'name' => str_replace('@', '', $this->telegram) . '_' . $server->code,
            'server_id' => $server->id,
            'user_id' => $this->id,
            'is_active' => true,
        ]);

        foreach ($configs as $config) {
            $success = $this->createConfig($config);
        }

        return $success;
    }
}
