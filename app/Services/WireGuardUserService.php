<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class WireGuardUserService
{
    private ?User $user;

    public function __construct(string $telegram)
    {
        $this->setUser($telegram);
    }

    public function setUser($telegram): static
    {
        $this->user = $this->getUser($telegram);

        return $this;
    }

    private function getUser($telegram): ?User
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
            ->whereTelegram('@' . $telegram)
            ->groupBy('users.id')
            ->first();
    }

    private function validateUser()
    {
        if (empty($this->user)) {
            return response()->json([
                'status' => false,
                'user' => null,
                'message' =>
                    "Я не вижу тебя в списках 😢\n\n"
                    . "Сообщи свой никнем @soussangler или @musiermoore"
            ], 404);
        }

        if ($this->user->hasDebt()) {
            return response()->json([
                'status' => false,
                'user' => $this->user,
                'type' => 'debt',
                'message' => "VPN не оплачен, необходимо пополнить баланс. Команда /balance"
            ], 403);
        }
    }
}
