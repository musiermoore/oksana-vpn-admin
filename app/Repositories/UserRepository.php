<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function create(array $attributes): User
    {
        return User::create($attributes);
    }

    public function update(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user->refresh();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function findOrFail(int $id): User
    {
        return User::query()->findOrFail($id);
    }
}
