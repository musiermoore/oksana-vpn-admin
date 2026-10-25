<?php

declare(strict_types=1);

namespace App\Http\Requests\XrayCustomConfig;

use App\DTOs\XrayCustomConfig\UpdateXrayCustomConfigUserPriceData;
use App\Http\Requests\DataFormRequest;

class UpdateXrayCustomConfigUserPriceRequest extends DataFormRequest
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
            'price' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
        ];
    }

    protected function laravelData(): string
    {
        return UpdateXrayCustomConfigUserPriceData::class;
    }
}
