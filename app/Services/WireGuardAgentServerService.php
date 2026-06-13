<?php

namespace App\Services;

use App\Models\Server;
use App\Services\Concerns\InteractsWithWireGuardAgentApi;
use Exception;

class WireGuardAgentServerService
{
    use InteractsWithWireGuardAgentApi;

    public function __construct(
        private readonly Server $server,
    ) {}

    public static function instance(Server $server): self
    {
        return new self($server);
    }

    public function install(): bool
    {
        try {
            return $this->installOrFail();
        } catch (Exception $exception) {
            report($exception);

            return false;
        }
    }

    public function installOrFail(): bool
    {
        $status = $this->getStatus();

        if (($status['installed'] ?? false) === true) {
            return true;
        }

        $response = $this->request()->post('/install');

        if ($response->successful() || $response->status() === 409) {
            return true;
        }

        $this->ensureSuccessful($response, '/install');

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listClients(): array
    {
        $response = $this->request()->get('/clients');
        $payload = $this->decodeJson($response);

        $this->ensureSuccessful($response, '/clients');

        return $payload['clients'] ?? $payload['data'] ?? $payload['items'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        $response = $this->request()->get('/status');

        if ($response->status() === 404) {
            return [];
        }

        $this->ensureSuccessful($response, '/status');

        return $this->decodeJson($response);
    }

    protected function getWireGuardAgentServer(): Server
    {
        return $this->server;
    }
}
