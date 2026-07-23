<?php

namespace App\Services\Subscriptions\Builders;

use App\DTOs\Subscription\NormalizedNode;
use App\Services\WireGuardSubscriptionLinkService;
use App\DTOs\Subscription\SubscriptionBuildResult;
use Illuminate\Support\Str;

class UriBuilder implements SubscriptionBuilder
{
    public function __construct(
        private readonly WireGuardSubscriptionLinkService $wireGuardSubscriptionLinkService,
    ) {}

    /**
     * @param  array<int, NormalizedNode>  $nodes
     */
    public function build(array $nodes): SubscriptionBuildResult
    {
        $lines = collect($nodes)
            ->map(fn (NormalizedNode $node) => $this->renameUri($node->uri, (string) ($node->meta['name'] ?? $node->serverName)))
            ->implode("\n");

        return new SubscriptionBuildResult(
            content: base64_encode($lines),
            contentType: 'text/plain; charset=UTF-8',
            fileExtension: 'txt',
        );
    }

    private function renameUri(string $uri, string $name): string
    {
        if (Str::startsWith($uri, 'wireguard://')) {
            return $this->wireGuardSubscriptionLinkService->fromConfigContent($uri, null, $name) ?? $uri;
        }

        if (! str_contains($uri, '#')) {
            return $uri.'#'.rawurlencode($name);
        }

        return Str::before($uri, '#').'#'.rawurlencode($name);
    }
}
