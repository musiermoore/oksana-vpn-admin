<?php

namespace App\Services;

use App\Models\User;
use App\Models\VlessConfig;
use Illuminate\Support\Facades\Http;

class VlessSubscriptionService
{
    public function __construct(private readonly User $user)
    {
    }

    public function getAllSubscriptions(): string
    {
        $links = $this->user->vlessConfigs()
            ->where('is_active', true)
            ->get()
            ->flatMap(fn (VlessConfig $config) => $this->getSubscriptionData($config))
            ->filter()
            ->unique()
            ->implode("\n");

        return base64_encode($links);
    }

    private function getSubscriptionData(VlessConfig $config): array
    {
        if (empty($config->sub_id)) {
            return [$config->getStaticLink()];
        }

        try {
            $response = Http::timeout(10)
                ->get($config->getSubscriptionLink())
                ->body();

            $decoded = base64_decode($response, true);

            // Some providers return plain text instead of base64
            if ($decoded === false) {
                $decoded = $response;
            }

            return collect(preg_split('/\r\n|\r|\n/', $decoded))
                ->map(fn ($line) => trim($line))
                ->filter(fn ($line) => !empty($line) && str_starts_with($line, 'vless://'))
                ->values()
                ->all();

        } catch (\Exception $e) {
            report($e);
            return [];
        }
    }
}
