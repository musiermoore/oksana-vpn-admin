<?php

declare(strict_types=1);

namespace App\Http\Requests\HealthCheck;

use App\DTOs\HealthCheck\UpdateInboundHealthCheckSettingsData;
use App\Http\Requests\DataFormRequest;

final class UpdateInboundHealthCheckSettingsRequest extends DataFormRequest
{
    protected function laravelData(): string
    {
        return UpdateInboundHealthCheckSettingsData::class;
    }

    public function rules(): array
    {
        return ['is_enabled' => ['required', 'boolean']];
    }
}
