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
            'server_id' => ['required', 'exists:servers,id'],
            'inbound_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toDto(): VlessConfigStoreData
    {
        $data = $this->validated();

        return new VlessConfigStoreData(
            userId: (int) $data['user_id'],
            serverId: (int) $data['server_id'],
            inboundId: (int) $data['inbound_id'],
        );
    }
}
