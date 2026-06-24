<?php

namespace App\Models;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
        'is_active',
        'max_devices',
        'traffic_limit_bytes',
        'subscription_expires_at',
        'welcome_text_seen_at',
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
            'subscription_expires_at' => 'datetime',
            'welcome_text_seen_at' => 'datetime',
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

    public function shadowsocksConfigs()
    {
        return $this->hasMany(ShadowsocksConfig::class);
    }

    public function activeVlessConfigs()
    {
        return $this->vlessConfigs()
            ->where('vless_configs.is_active', true)
            ->where('vless_configs.enable', true);
    }

    public function activeShadowsocksConfigs()
    {
        return $this->shadowsocksConfigs()
            ->where('shadowsocks_configs.is_active', true)
            ->where('shadowsocks_configs.enable', true);
    }

    public function tokens()
    {
        return $this->hasMany(UserToken::class);
    }

    public function telegramAppTokens(): HasMany
    {
        return $this->hasMany(TelegramAppToken::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function activeConnections(): HasMany
    {
        return $this->hasMany(ActiveConnection::class);
    }

    public function blockedConfigs(): HasMany
    {
        return $this->hasMany(BlockedConfig::class);
    }

    public function serverStats(): HasMany
    {
        return $this->hasMany(UserServerStat::class);
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

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function activeSubscription(?Carbon $date = null): HasOne
    {
        if (empty($date)) {
            $date = now();
        }

        return $this->hasOne(UserSubscription::class)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderByDesc('user_subscriptions.created_at');
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class)
            ->ofMany('end_date', 'max');
    }

    public function latestActiveOrFutureSubscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class)
            ->whereDate('end_date', '>=', now())
            ->ofMany('end_date', 'max');
    }

    public function getFullNameAttribute(): string
    {
        return $this->attributes['telegram']
            .' ('.$this->attributes['name'].')'
            .($this->is_active ? '' : ' - Удалён');
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
            report($exception);

            return false;
        }
    }

    public function createConfigOrFail(array $attributes): Config
    {
        DB::beginTransaction();

        try {
            $config = $this->configs()->create($attributes);
            $config->createWgConfigOrFail();

            DB::commit();

            return $config;
        } catch (Exception $exception) {
            DB::rollBack();
            report($exception);

            throw new RuntimeException(
                sprintf(
                    'Failed to create WireGuard config [%s] for server [%s]: %s',
                    $attributes['name'] ?? 'unknown',
                    $attributes['server_id'] ?? 'unknown',
                    $exception->getMessage()
                ),
                previous: $exception
            );
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

    public function hasActiveSubscription(?Carbon $date = null): bool
    {
        if ($date === null && $this->relationLoaded('activeSubscription')) {
            return $this->activeSubscription !== null;
        }

        return $this->activeSubscription($date)->exists();
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

    public function getDefaultConfigNameForServer(Server $server): string
    {
        $telegram = trim((string) $this->telegram, '@');

        return ($telegram !== '' ? $telegram : 'user_'.$this->id).'_'.$server->code;
    }

    public function createDefaultConfigs(?Collection $servers = null): bool
    {
        $success = true;

        $servers ??= Server::query()
            ->where('is_ready', true)
            ->wireGuard()
            ->get();

        $existingServerIds = $this->configs()
            ->pluck('server_id')
            ->all();

        $configs = $servers
            ->reject(fn (Server $server) => in_array($server->id, $existingServerIds, true))
            ->map(fn (Server $server) => [
                'name' => $this->getDefaultConfigNameForServer($server),
                'server_id' => $server->id,
                'user_id' => $this->id,
                'is_active' => true,
            ]);

        foreach ($configs as $config) {
            $success = $this->createConfig($config) && $success;
        }

        return $success;
    }
}
