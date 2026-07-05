<?php

declare(strict_types=1);

namespace App\Http\Requests\TaxSetting;

use App\DTOs\Tax\TaxSettingData;
use App\Http\Requests\DataFormRequest;

class UpdateTaxSettingRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1000'],
            'service_name' => ['required', 'string', 'max:255'],
        ];
    }

    protected function dtoClass(): string
    {
        return TaxSettingData::class;
    }
}
