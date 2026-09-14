<?php

declare(strict_types=1);

namespace App\Services\XuiServices\V3_2_8;

use App\Models\VlessConfig;
use App\Services\XuiConfigService as BaseXuiConfigService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class XuiConfigService extends BaseXuiConfigService
{
    protected function postClientSettings(int $inboundId, array $settings): array
    {
        $response = $this->sendXuiRequest(
            'POST',
            '/panel/api/clients/add',
            $this->buildSingleInboundClientPayload($inboundId, $settings),
            context: ['inbound_id' => $inboundId],
        )
            ->throw();

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    protected function usesTrafficEndpointForEnableState(): bool
    {
        return false;
    }

    /**
     * @return array<int, string>
     */
    protected function getClientTrafficPaths(string $email): array
    {
        return [
            '/panel/api/clients/traffic/'.urlencode($email),
        ];
    }

    protected function updateClient(VlessConfig $config, array $client): Response
    {
        $path = '/panel/api/clients/update/'.urlencode($config->name);

        return $this->sendXuiRequest(
            'POST',
            $path,
            $client['settings'],
            fn (PendingRequest $request): PendingRequest => $request->withOptions([
                    'query' => [
                        'inboundIds' => (string) $client['inbound_id'],
                    ],
                ]),
            [
                'client_identifier' => $config->name,
                'inbound_id' => (int) $client['inbound_id'],
            ],
        )->throw();
    }

    protected function postUpdateClientByIdentifier(string $identifier, array $client): Response
    {
        $path = '/panel/api/clients/update/'.urlencode($identifier);

        return $this->sendXuiRequest(
            'POST',
            $path,
            $client['settings'],
            fn (PendingRequest $request): PendingRequest => $request->withOptions([
                    'query' => [
                        'inboundIds' => (string) $client['inbound_id'],
                    ],
                ]),
            [
                'client_identifier' => $identifier,
                'inbound_id' => (int) $client['inbound_id'],
            ],
        )->throw();
    }
}
