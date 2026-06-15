<?php

namespace App\Services\XuiServices\V3_2_8;

use App\Models\VlessConfig;
use App\Services\XuiConfigService as BaseXuiConfigService;
use Illuminate\Http\Client\Response;

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

    protected function usesTrafficEndpointForEnableState(): bool
    {
        return false;
    }

    protected function updateClient(VlessConfig $config, array $client): Response
    {
        return $this->getRequest()
            ->withOptions([
                'query' => [
                    'inboundIds' => (string) $client['inbound_id'],
                ],
            ])
            ->post('/panel/api/clients/update/'.urlencode($config->name), $client['settings'])
            ->throw();
    }

    protected function postUpdateClientByIdentifier(string $identifier, array $client): Response
    {
        return $this->getRequest()
            ->withOptions([
                'query' => [
                    'inboundIds' => (string) $client['inbound_id'],
                ],
            ])
            ->post('/panel/api/clients/update/'.urlencode($identifier), $client['settings'])
            ->throw();
    }
}
