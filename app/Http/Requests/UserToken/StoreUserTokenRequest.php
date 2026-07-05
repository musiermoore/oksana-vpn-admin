<?php

declare(strict_types=1);

namespace App\Http\Requests\UserToken;

use App\DTOs\UserToken\UserTokenData;
use App\Http\Requests\DataFormRequest;

class StoreUserTokenRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
        ];
    }

    protected function dtoClass(): string
    {
        return UserTokenData::class;
    }
}
