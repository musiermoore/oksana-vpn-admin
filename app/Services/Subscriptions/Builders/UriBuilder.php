<?php

namespace App\Services\Subscriptions\Builders;

use App\DTOs\Subscription\NormalizedNode;
use App\DTOs\Subscription\SubscriptionBuildResult;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class UriBuilder implements SubscriptionBuilder
{
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
            $renamed = $this->replaceFragment($uri, $name);

            Log::info('connect.wg', [
                'in' => $uri,
                'out' => $renamed,
            ]);

            return $renamed;
        }

        return $this->replaceFragment($uri, $name);
    }

    private function replaceFragment(string $uri, string $name): string
    {
        if (! str_contains($uri, '#')) {
            return $uri.'#'.rawurlencode($name);
        }

        return Str::before($uri, '#').'#'.rawurlencode($name);
    }
}
