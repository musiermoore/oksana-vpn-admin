<?php

namespace App\Services\Crud;

use App\DTOs\UserToken\UserTokenData;
use App\Models\UserToken;
use App\Repositories\UserRepository;
use App\Repositories\UserTokenRepository;
use Illuminate\Support\Str;

class UserTokenCrudService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserTokenRepository $tokens,
    ) {}

    public function create(UserTokenData $data): UserToken
    {
        $user = $this->users->findOrFail($data->userId);

        return $this->tokens->createForUser($user, [
            'token' => Str::random(40),
            'password' => Str::random(10),
        ]);
    }

    public function delete(UserToken $userToken): void
    {
        $this->tokens->delete($userToken);
    }
}
