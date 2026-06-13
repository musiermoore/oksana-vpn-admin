<?php

namespace App\Services;

use App\Models\Config;
use App\Models\Server;
use App\Services\Concerns\InteractsWithWireGuardAgentApi;
use App\Services\Contracts\WireGuardConfigServiceContract;
use Exception;
use Illuminate\Support\Facades\File;

class WireGuardAgentConfigService implements WireGuardConfigServiceContract
{
    use InteractsWithWireGuardAgentApi;

    private Config $config;
    private Server $server;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->server = $config->server;
    }

    public static function instance(Config $config): self
    {
        return new self($config);
    }

    public function create(): bool
    {
        try {
            return $this->createOrFail();
        } catch (Exception $exception) {
            report($exception);

            return false;
        }
    }

    public function createOrFail(): bool
    {
        WireGuardAgentServerService::instance($this->server)->installOrFail();

        $response = $this->request()->post('/clients', $this->buildCreatePayload());
        $payload = $this->decodeJson($response);

        $this->ensureSuccessful($response, '/clients');
        $this->storeClientConfig($payload);

        return true;
    }

    public function delete(): bool
    {
        try {
            $response = $this->request()->delete('/clients/'.$this->encodedClientName());

            if ($response->status() !== 404) {
                $this->ensureSuccessful($response, '/clients/'.$this->encodedClientName());
            }

            $this->deleteStoredConfig();

            return true;
        } catch (Exception $exception) {
            report($exception);

            return false;
        }
    }

    public function enable(): bool
    {
        try {
            $response = $this->request()->post('/clients/'.$this->encodedClientName().'/enable');
            $this->ensureSuccessful($response, '/clients/'.$this->encodedClientName().'/enable');

            return true;
        } catch (Exception $exception) {
            report($exception);

            return false;
        }
    }

    public function disable(): bool
    {
        try {
            $response = $this->request()->post('/clients/'.$this->encodedClientName().'/disable');
            $this->ensureSuccessful($response, '/clients/'.$this->encodedClientName().'/disable');

            return true;
        } catch (Exception $exception) {
            report($exception);

            return false;
        }
    }

    public function setLimit(int|string $limit): bool
    {
        return false;
    }

    public function removeLimit(int|string $limit): bool
    {
        return false;
    }

    public function getClientConfig(): string
    {
        $response = $this->request()->get('/clients/'.$this->encodedClientName().'/config');
        $this->ensureSuccessful($response, '/clients/'.$this->encodedClientName().'/config');

        $contentType = (string) $response->header('Content-Type');

        if (str_contains($contentType, 'application/json')) {
            $payload = $this->decodeJson($response);

            return $this->extractConfigBody($payload);
        }

        return trim($response->body());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCreatePayload(): array
    {
        return [
            'name' => $this->config->name,
            'user_id' => $this->config->user_id,
            'server_id' => $this->config->server_id,
            'telegram' => $this->config->user?->telegram,
            'description' => $this->config->description,
        ];
    }

    private function encodedClientName(): string
    {
        return rawurlencode($this->config->name);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeClientConfig(array $payload): void
    {
        $configBody = $this->extractConfigBody($payload);

        if ($configBody === '') {
            $configBody = $this->getClientConfig();
        }

        $directory = dirname($this->config->path);

        File::ensureDirectoryExists($directory);
        File::put($this->config->path, $configBody.PHP_EOL);
    }

    private function deleteStoredConfig(): void
    {
        if (File::exists($this->config->path)) {
            File::delete($this->config->path);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractConfigBody(array $payload): string
    {
        foreach (['config', 'client_config', 'content'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        $data = $payload['data'] ?? null;

        if (is_array($data)) {
            foreach (['config', 'client_config', 'content'] as $key) {
                $value = $data[$key] ?? null;

                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        return '';
    }

    protected function getWireGuardAgentServer(): Server
    {
        return $this->server;
    }
}
