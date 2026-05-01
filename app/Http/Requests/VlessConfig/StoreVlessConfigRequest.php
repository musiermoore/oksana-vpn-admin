<?php

namespace App\Http\Requests\VlessConfig;

use App\DTOs\VlessConfig\VlessConfigStoreData;
use Illuminate\Foundation\Http\FormRequest;

class StoreVlessConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'config_id' => ['required', 'exists:vless_configs,id'],
        ];
    }

    public function toDto(): VlessConfigStoreData
    {
        $data = $this->validated();

        return new VlessConfigStoreData(
            userId: (int) $data['user_id'],
            configId: (int) $data['config_id'],
        );
    }
}
