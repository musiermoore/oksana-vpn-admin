<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SubscriptionProfileHealthCheck;
use App\Models\User;
use App\Models\XrayCustomConfig;
use App\Services\Subscriptions\XrayCustomConfigService;

final class XrayCustomProfileHealthChecker
{
    public function __construct(
        private readonly XrayCustomConfigService $customConfigs,
        private readonly SubscriptionProfileHealthRecorder $recorder,
    ) {}

    public function check(User $user): void
    {
        $this->recorder->runFlow('xray-custom', fn () => $this->checkConfigs($user));
    }

    private function checkConfigs(User $user): void
    {
        $configs = XrayCustomConfig::query()
            ->active()
            ->ordered()
            ->get();

        foreach ($configs as $config) {
            $profiles = json_decode($this->customConfigs->build($user, $config)->content, true);
            $profiles = is_array($profiles) ? $profiles : [];

            if ($profiles === []) {
                $this->recorder->record('xray-custom', 'custom-'.$config->id, $config->name, [
                    'status' => SubscriptionProfileHealthCheck::STATUS_UNSUPPORTED,
                    'results' => [],
                    'error' => 'Для этой пользовательской подписки не найдены конфигурации технического пользователя.',
                ]);

                continue;
            }

            foreach ($profiles as $index => $profile) {
                if (! is_array($profile)) {
                    continue;
                }

                $name = trim((string) ($profile['remarks'] ?? '')) ?: $config->name.' #'.($index + 1);
                $key = sha1((string) ($profile['remarks'] ?? 'custom-'.$config->id.'-'.$index)
                    .'|'.json_encode($profile, JSON_UNESCAPED_SLASHES));
                $this->recorder->queueProbe(
                    'xray-custom',
                    $key,
                    $name,
                    'json-profile',
                    $profile,
                );
            }
        }

        $this->recorder->recordEmpty('xray-custom', $configs->all());
    }
}
