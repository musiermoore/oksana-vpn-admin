<?php

declare(strict_types=1);

namespace App\Http\Requests\VlessConfig;

use App\DTOs\VlessConfig\VlessConfigStoreData;
use App\Http\Requests\DataFormRequest;

class StoreVlessConfigRequest extends DataFormRequest
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

    protected function dtoClass(): string
    {
        return VlessConfigStoreData::class;
    }
}
