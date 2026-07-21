<?php

declare(strict_types=1);

namespace App\Http\Requests\XrayConfig;

use App\DTOs\XrayConfig\XrayConfigStoreData;
use App\Http\Requests\DataFormRequest;
use App\Models\Server;
use Illuminate\Validation\Rule;

class StoreXrayConfigRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'protocol' => ['required', 'string', 'in:vless'],
            'user_id' => ['required', 'exists:users,id'],
            'server_id' => [
                'required',
                Rule::exists('servers', 'id')->where(
                    fn ($query) => $query
                        ->where('type', Server::TYPE_VLESS)
                        ->where('is_active', true)
                ),
            ],
            'inbound_id' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function dtoClass(): string
    {
        return XrayConfigStoreData::class;
    }
}
