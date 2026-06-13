<?php

namespace App\Services\Concerns;

use App\Models\Server;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

trait InteractsWithWireGuardAgentApi
{
    abstract protected function getWireGuardAgentServer(): Server;

    protected function request(): PendingRequest
    {
        $server = $this->getWireGuardAgentServer();

        $request = Http::acceptJson()
            ->asJson()
            ->timeout(30)
            ->baseUrl($this->resolveBaseUrl());

        if (filled($server->panel_username) && filled($server->panel_password)) {
            $request = $request->withBasicAuth(
                (string) $server->panel_username,
                (string) $server->panel_password,
            );
        }

        return $request;
    }

    protected function resolveBaseUrl(): string
    {
        $server = $this->getWireGuardAgentServer();
        $baseUrl = trim((string) $server->panel_link);

        if ($baseUrl !== '') {
            return rtrim($baseUrl, '/');
        }

        return rtrim($server->getScheme().'://'.$server->getHost(), '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(Response $response): array
    {
        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    protected function ensureSuccessful(Response $response, string $endpoint): void
    {
        if ($response->successful()) {
            return;
        }

        $server = $this->getWireGuardAgentServer();
        $payload = $this->decodeJson($response);
        $message = trim((string) ($payload['message'] ?? $response->body()));

        throw new RuntimeException(sprintf(
            'WireGuard Agent API request failed for server [%s] on [%s] with status [%s]%s',
            $server->id,
            $endpoint,
            $response->status(),
            $message !== '' ? ': '.$message : '.',
        ));
    }
}
