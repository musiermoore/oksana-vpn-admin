<?php

namespace App\Services;

use App\Models\User;

class WireGuardUserService
{
    private ?User $user;

    public function __construct(string $telegram)
    {
        $this->setUser($telegram);
    }

    public function setUser($telegram): static
    {
        $this->user = UserApiService::instance($telegram)->getUser();

        return $this;
    }

    private function validateUser()
    {
        if (empty($this->user)) {
            return response()->json([
                'status' => false,
                'user' => null,
                'message' =>
                    "Я не вижу тебя в списках 😢\n\n"
                    . "Сообщи свой никнем @soussangler"
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
