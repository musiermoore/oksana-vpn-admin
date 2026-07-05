<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TaxSetting;

class TaxSettingRepository
{
    public function firstOrCreateDefault(): TaxSetting
    {
        return TaxSetting::query()->firstOrCreate([], [
            'service_name' => 'Настройка сетевой конфигурации',
        ]);
    }

    public function first(): ?TaxSetting
    {
        return TaxSetting::query()->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TaxSetting $settings, array $attributes): TaxSetting
    {
        $settings->update($attributes);

        return $settings->refresh();
    }
}
