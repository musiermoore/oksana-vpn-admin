<?php

declare(strict_types=1);

namespace App\Http\Requests\Config;

use App\DTOs\Config\ConfigBulkStoreData;
use App\Http\Requests\DataFormRequest;
use App\Models\Server;
use Illuminate\Validation\Rule;

class StoreBulkConfigRequest extends DataFormRequest
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
                Rule::exists('servers', 'id')->where(
                    fn ($query) => $query
                        ->whereIn('type', Server::wireGuardTypes())
                        ->where('is_active', true)
                ),
            ],
        ];
    }

    protected function dtoClass(): string
    {
        return ConfigBulkStoreData::class;
    }
}
