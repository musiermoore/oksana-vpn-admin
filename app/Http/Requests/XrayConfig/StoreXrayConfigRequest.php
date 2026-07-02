<?php

namespace App\Http\Requests\XrayConfig;

use App\DTOs\XrayConfig\XrayConfigStoreData;
use App\Models\Server;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreXrayConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'protocol' => ['required', 'string', 'in:vless,shadowsocks'],
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

    public function toDto(): XrayConfigStoreData
    {
        $data = $this->validated();

        return new XrayConfigStoreData(
            protocol: (string) $data['protocol'],
            userId: (int) $data['user_id'],
            serverId: (int) $data['server_id'],
            inboundId: (int) $data['inbound_id'],
        );
    }
}
