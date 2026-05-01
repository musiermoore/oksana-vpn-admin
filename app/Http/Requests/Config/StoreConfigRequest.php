<?php

namespace App\Http\Requests\Config;

use App\DTOs\Config\ConfigCreateItemData;
use App\DTOs\Config\ConfigStoreData;
use Illuminate\Foundation\Http\FormRequest;

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
            'configs.*.name' => ['required', 'string', 'max:255'],
            'configs.*.server_id' => ['required', 'exists:servers,id'],
        ];
    }

    public function toDto(): ConfigStoreData
    {
        $data = $this->validated();

        return new ConfigStoreData(
            userId: (int) $data['user_id'],
            configs: array_map(
                fn (array $config) => new ConfigCreateItemData(
                    name: $config['name'],
                    serverId: (int) $config['server_id'],
                ),
                $data['configs'],
            ),
        );
    }
}
