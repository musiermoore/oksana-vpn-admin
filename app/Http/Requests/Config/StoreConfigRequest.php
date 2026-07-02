<?php

namespace App\Http\Requests\Config;

use App\DTOs\Config\ConfigCreateItemData;
use App\DTOs\Config\ConfigStoreData;
use App\Models\Server;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConfigRequest extends FormRequest
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

    public function toDto(): ConfigStoreData
    {
        $data = $this->validated();

        return new ConfigStoreData(
            userId: (int) $data['user_id'],
            configs: array_map(
                fn (array $config) => new ConfigCreateItemData(
                    serverId: (int) $config['server_id'],
                    description: $config['description'] ?? null,
                ),
                $data['configs'],
            ),
        );
    }
}
