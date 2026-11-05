<?php

declare(strict_types=1);

namespace App\Http\Requests\XrayGeodata;

use App\DTOs\XrayGeodata\XrayGeodataData;
use App\Http\Requests\DataFormRequest;
use Illuminate\Validation\Validator;

class SaveXrayGeodataRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'geoip_url' => ['nullable', 'url', 'max:2000'],
            'geosite_url' => ['nullable', 'url', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->input('is_active', true)]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (blank($this->input('geoip_url')) && blank($this->input('geosite_url'))) {
                $validator->errors()->add('geoip_url', 'Укажите URL хотя бы одного geodata-файла.');
            }
        });
    }

    protected function laravelData(): string
    {
        return XrayGeodataData::class;
    }
}
