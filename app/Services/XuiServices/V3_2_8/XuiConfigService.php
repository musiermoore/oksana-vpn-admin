<?php

namespace App\Services\XuiServices\V3_2_8;

use App\Services\XuiConfigService as BaseXuiConfigService;

class XuiConfigService extends BaseXuiConfigService
{
    protected function postClientSettings(int $inboundId, array $settings): array
    {
        $response = $this->getRequest()
            ->post('/panel/api/clients/add', [
                'client' => array_merge([
                    'email' => '',
                    'subId' => '',
                    'id' => '',
                    'password' => '',
                    'auth' => '',
                    'flow' => '',
                    'security' => 'auto',
                    'totalGB' => 0,
                    'expiryTime' => 0,
                    'reset' => 0,
                    'limitIp' => 0,
                    'tgId' => 0,
                    'group' => '',
                    'comment' => '',
                    'enable' => true,
                ], array_filter($settings, fn (mixed $value) => $value !== null)),
                'inboundIds' => [$inboundId],
            ])
            ->throw();

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }
}
