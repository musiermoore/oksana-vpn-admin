<?php

declare(strict_types=1);

namespace App\Http\Requests\XrayRouting;

use App\DTOs\XrayRouting\XrayRoutingImportData;
use App\Http\Requests\DataFormRequest;

class ImportXrayRoutingSettingsRequest extends DataFormRequest
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
            'settings_json' => ['required', 'string', 'json'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function dtoPayload(): array
    {
        return [
            'payload' => json_decode((string) $this->validated('settings_json'), true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    protected function dtoClass(): string
    {
        return XrayRoutingImportData::class;
    }
}
