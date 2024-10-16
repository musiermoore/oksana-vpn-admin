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
        'join_at'
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
        } catch (Exception $exception) {
            DB::rollBack();

            return false;
        }

        return true;
    }
}
