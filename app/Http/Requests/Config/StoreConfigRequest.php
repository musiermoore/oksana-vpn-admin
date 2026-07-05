<?php

declare(strict_types=1);

namespace App\Http\Requests\Config;

use App\DTOs\Config\ConfigStoreData;
use App\Http\Requests\DataFormRequest;
use App\Models\Server;
use Illuminate\Validation\Rule;

class StoreConfigRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'configs' => ['required', 'array', 'min:1'],
            'configs.*.server_id' => [
                'required',
                Rule::exists('servers', 'id')->where(
                    fn ($query) => $query
                        ->whereIn('type', Server::wireGuardTypes())
                        ->where('is_active', true)
                ),
            ],
            'configs.*.description' => ['nullable', 'string'],
        ];
    }

    protected function dtoClass(): string
    {
        return ConfigStoreData::class;
    }
}
