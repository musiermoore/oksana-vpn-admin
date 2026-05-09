<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class XuiConfigService
{
    private ?string $session = null;

    public function __construct(
        private readonly Server $server,
    )
    {
        $this->setSession();
    }

    public function getInbounds(): array
    {
        $inboundsResponse = $this->getRequest()
            ->get('/panel/api/inbounds/list')
            ->throw();

        $payload = $inboundsResponse->json();

        if (! is_array($payload)) {
            return [];
        }

        $inbounds = $payload['obj'] ?? $payload['data'] ?? $payload;

        return is_array($inbounds) ? array_values($inbounds) : [];
    }

    private function setSession(): void
    {
        $response = $this->getRequest()
            ->asForm()
            ->post('/login', [
                'username' => $this->server->panel_username,
                'password' => $this->server->panel_password,
            ])
            ->throw();

        $cookie = $response->cookies()->getCookieByName('3x-ui')
            ?? $response->cookies()->getCookieByName('x-ui');

        if (! $cookie?->getValue()) {
            throw new RuntimeException("Unable to authenticate with 3x-ui for server [{$this->server->id}]");
        }

        $this->session = $cookie?->getValue();
    }

    public function getSession(): ?string
    {
        return $this->session;
    }

    private function getRequest(): PendingRequest
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($this->session) {
            $headers['Cookie'] = '3x-ui=' . $this->session;
        }

        $options = [];

        if (config('telegram.proxy')) {
            $options['proxy'] = config('telegram.proxy');
        }

        return Http::baseUrl(rtrim($this->server->panel_link, '/'))
            ->timeout(15)
            ->withHeaders($headers)
            ->withOptions($options);
    }
}
