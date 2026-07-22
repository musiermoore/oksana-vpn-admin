<?php

namespace App\Services;

use App\Models\Server;
use App\Models\VlessConfig;
use RuntimeException;

class XrayClientAccessService
{
    public function __construct(
        private readonly XrayConfigLocatorService $configLocator,
    ) {}

    public function disable(string $configType, int $configId): void
    {
        $this->setEnabled($configType, $configId, false);
    }

    public function enable(string $configType, int $configId): void
    {
        $this->setEnabled($configType, $configId, true);
    }

    private function setEnabled(string $configType, int $configId, bool $enabled): void
    {
        $config = $this->configLocator->findModel($configType, $configId);

        if (! $config) {
            throw new RuntimeException("Config [{$configType}:{$configId}] not found.");
        }

        $server = Server::query()->find($config->server_id);

        if (! $server) {
            throw new RuntimeException("Server for config [{$configType}:{$configId}] not found.");
        }

        $service = XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server);

        if ($config instanceof VlessConfig) {
            $inboundId = $config->getResolvedInboundId();

            if ($inboundId === null) {
                throw new RuntimeException("Inbound for config [{$configType}:{$configId}] was not found.");
            }

            $service->setClientEnabledByIdentifier(
                identifier: $config->name,
                email: $config->name,
                inboundId: $inboundId,
                enabled: $enabled,
                context: [
                    'protocol' => (string) ($config->protocol ?: 'vless'),
                    'password' => $config->password,
                    'auth' => $config->auth,
                    'uuid' => $config->uuid,
                    'subId' => $config->sub_id,
                    'flow' => $config->flow,
                ],
            );

            $config->forceFill(['enable' => $enabled])->save();

            return;
        }

        throw new RuntimeException("Unsupported config type [{$configType}]");
    }
}
