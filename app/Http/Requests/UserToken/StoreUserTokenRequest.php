<?php

namespace App\Http\Requests\UserToken;

use App\DTOs\UserToken\UserTokenData;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserTokenRequest extends FormRequest
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

    public function toDto(): UserTokenData
    {
        $data = $this->validated();

        return new UserTokenData(userId: (int) $data['user_id']);
    }
}
