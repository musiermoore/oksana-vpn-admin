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
}
