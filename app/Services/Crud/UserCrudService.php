<?php

namespace App\Services\Crud;

use App\DTOs\User\UserData;
use App\Models\User;
use App\Repositories\UserRepository;

class UserCrudService
{
    public function __construct(
        private readonly UserRepository $users,
    ) {}

    public function create(UserData $data): User
    {
        $user = $this->users->create($data->toArray());

        if ($data->createConfigs) {
            $user->createDefaultConfigs();
        }

        return $user;
    }

    public function update(User $user, UserData $data): User
    {
        return $this->users->update($user, $data->toArray());
    }

    public function delete(User $user): void
    {
        $this->users->delete($user);
    }
}
