<?php

namespace App\Http\Requests\Config;

use App\DTOs\Config\ConfigBulkStoreData;
use App\Models\Server;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBulkConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'server_id' => [
                'required',
                Rule::exists('servers', 'id')->where(fn ($query) => $query->whereIn('type', Server::wireGuardTypes())),
            ],
        ];
    }

    public function toDto(): ConfigBulkStoreData
    {
        $data = $this->validated();

        return new ConfigBulkStoreData(serverId: (int) $data['server_id']);
    }
}
