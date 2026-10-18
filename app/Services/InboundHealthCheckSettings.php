<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\XrayHealthCheckSetting;

final class InboundHealthCheckSettings
{
    private const SETTINGS_ID = 1;

    public function enabled(): bool
    {
        return $this->setting()->is_enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->setting()->forceFill(['is_enabled' => $enabled])->save();
    }

    private function setting(): XrayHealthCheckSetting
    {
        return XrayHealthCheckSetting::query()->firstOrCreate(
            ['id' => self::SETTINGS_ID],
            ['is_enabled' => true],
        );
    }
}
