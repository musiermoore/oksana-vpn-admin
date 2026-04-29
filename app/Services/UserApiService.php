<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserExtraPayment;
use Illuminate\Support\Facades\DB;

class UserApiService
{
    public string $telegram;

    public function __construct(string $telegram)
    {
        $this->telegram = $telegram;
    }

    public static function instance(string $telegram): UserApiService
    {
        return new self($telegram);
    }

    public function getUser(): ?User
    {
        return User::query()
            ->with([
                'configs' => function ($query) {
                    $query->select([
                        'id', 'user_id', 'name'
                    ]);
                }
            ])
            ->select([
                'users.id', 'users.telegram', 'users.name', 'users.telegram_id',
                DB::raw('SUM(IFNULL(current_payments.amount, 0)) + IFNULL(user_extra_payments.amount, 0) + IFNULL(users.extra_payment, 0) AS payment_amount')
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
            ->leftJoinSub(
                UserExtraPayment::select(['user_id', DB::raw('SUM(amount) AS amount')])->groupBy('user_id'),
                'user_extra_payments', 'user_extra_payments.user_id', '=', 'users.id'
            )
            ->whereTelegram('@' . $this->telegram)
            ->groupBy('users.id')
            ->first();
    }
}
