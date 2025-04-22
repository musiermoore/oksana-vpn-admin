<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'extra_payment',
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
        ];
    }

    public function configs()
    {
        return $this->hasMany(Config::class);
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
        $user = self::query()
            ->select([
                'users.id', 'users.telegram',
                DB::raw('SUM(current_payments.amount) AS payment_amount')
            ])
            ->withSum('transactions', 'amount')
            ->leftJoin('current_payments', function ($join) {
                $join
                    ->where(function ($query) {
                        $query
                            ->where('start_date', '>=', DB::raw('users.join_at'))
                            ->orWhereNull('join_at');
                    })
                    ->where('start_date', '<=', DB::raw('CURRENT_TIMESTAMP()'));
            })
            ->groupBy('users.id')
            ->find($this->id);

        return max(0, $user->payment_amount - $user->transactions_sum_amount) > 0;
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
