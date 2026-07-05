<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TaxSetting\UpdateTaxSettingRequest;
use App\Services\Tax\TaxSettingsService;
use Illuminate\Http\RedirectResponse;

class TaxSettingController extends Controller
{
    public function __construct(
        private readonly TaxSettingsService $taxSettings,
    ) {}

    public function edit()
    {
        $settings = $this->taxSettings->getOrCreate();

        return $this->inertia('TaxSettings/Edit', [
            'settings' => [
                'login' => $settings->login,
                'password' => '',
                'service_name' => $settings->service_name ?: 'Настройка сетевой конфигурации',
            ],
        ]);
    }

    public function update(UpdateTaxSettingRequest $request): RedirectResponse
    {
        $this->taxSettings->update($request->toDto());

        return redirect()
            ->route('tax-settings.edit')
            ->with('success', 'Налоговые настройки сохранены.');
    }
}
