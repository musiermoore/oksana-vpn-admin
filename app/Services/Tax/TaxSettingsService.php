<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\DTOs\Tax\TaxSettingData;
use App\Models\TaxSetting;
use App\Repositories\TaxSettingRepository;

class TaxSettingsService
{
    public function __construct(
        private readonly TaxSettingRepository $settings,
    ) {}

    public function getOrCreate(): TaxSetting
    {
        return $this->settings->firstOrCreateDefault();
    }

    public function getCurrent(): ?TaxSetting
    {
        return $this->settings->first();
    }

    public function update(TaxSettingData $data): TaxSetting
    {
        $settings = $this->settings->firstOrCreateDefault();

        $attributes = [
            'login' => $data->login,
            'service_name' => $data->serviceName,
        ];

        if ($data->password !== null && $data->password !== '') {
            $attributes['password'] = $data->password;
        }

        return $this->settings->update($settings, $attributes);
    }
}
