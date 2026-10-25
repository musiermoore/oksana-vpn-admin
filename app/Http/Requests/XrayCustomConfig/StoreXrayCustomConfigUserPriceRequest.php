<?php

declare(strict_types=1);

namespace App\Http\Requests\XrayCustomConfig;

use App\DTOs\XrayCustomConfig\XrayCustomConfigUserPriceData;
use App\Http\Requests\DataFormRequest;
use Illuminate\Validation\Rule;

class StoreXrayCustomConfigUserPriceRequest extends DataFormRequest
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
            'xray_custom_config_id' => ['required', 'integer', Rule::exists('xray_custom_configs', 'id')],
            'price' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
        ];
    }

    protected function laravelData(): string
    {
        return XrayCustomConfigUserPriceData::class;
    }
}
