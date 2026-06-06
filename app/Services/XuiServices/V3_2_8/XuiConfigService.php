<?php

namespace App\Services\XuiServices\V3_2_8;

use App\Services\XuiConfigService as BaseXuiConfigService;

class XuiConfigService extends BaseXuiConfigService
{
    protected function postClientSettings(int $inboundId, array $settings): array
    {
        $response = $this->getRequest()
            ->post('/panel/api/clients/add', $this->buildSingleInboundClientPayload($inboundId, $settings))
            ->throw();

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }
}
