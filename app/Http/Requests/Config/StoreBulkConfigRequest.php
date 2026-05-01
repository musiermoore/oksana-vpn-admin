<?php

namespace App\Http\Requests\Config;

use App\DTOs\Config\ConfigBulkStoreData;
use Illuminate\Foundation\Http\FormRequest;

class StoreBulkConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'server_id' => ['required', 'exists:servers,id'],
        ];
    }

    public function toDto(): ConfigBulkStoreData
    {
        $data = $this->validated();

        return new ConfigBulkStoreData(serverId: (int) $data['server_id']);
    }
}
