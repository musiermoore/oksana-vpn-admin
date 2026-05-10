<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'password' => $this->password,
            'expires_at' => $this->expires_at,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'telegram' => $this->user->telegram,
                'full_name' => $this->user->full_name,
            ] : null,
            'links' => [
                'show' => route('user-tokens.show', $this->resource),
                'destroy' => route('user-tokens.destroy', $this->resource),
                'public_configs' => route('users.configs', $this->token),
            ],
        ];
    }
}
