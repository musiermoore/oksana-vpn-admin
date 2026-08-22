<?php

namespace App\Services\Subscriptions;

use App\DTOs\Subscription\NormalizedNode;

class NodeNameService
{
    /**
     * @param  array<int, NormalizedNode>  $nodes
     * @return array<string, string>
     */
    public function buildNames(array $nodes): array
    {
        $grouped = collect($nodes)
            ->groupBy(fn (NormalizedNode $node) => $this->groupingKey($node));

        $names = [];

        foreach ($grouped as $group) {
            $sorted = $group
                ->sortBy([
                    fn (NormalizedNode $node) => $node->serverId,
                    fn (NormalizedNode $node) => $node->configId,
                    fn (NormalizedNode $node) => $node->uri,
                ])
                ->values();

            $needsNumbering = $sorted->count() > 1;

            foreach ($sorted as $index => $node) {
                $baseName = $this->buildBaseName($node);

                $names[$node->id] = $needsNumbering
                    ? $baseName.' #'.($index + 1)
                    : $baseName;
            }
        }

        return $names;
    }

    private function groupingKey(NormalizedNode $node): string
    {
        if ((bool) ($node->meta['hide_main_node_name'] ?? false)) {
            return implode('|', [
                'custom-name',
                mb_strtolower((string) ($node->meta['proxy_name'] ?? $node->serverName)),
            ]);
        }

        return implode('|', [
            mb_strtolower($node->serverName),
            mb_strtolower($node->protocol),
            mb_strtolower($node->transport),
        ]);
    }

    private function buildBaseName(NormalizedNode $node): string
    {
        if ((bool) ($node->meta['hide_main_node_name'] ?? false)) {
            return (string) ($node->meta['proxy_name'] ?? $node->serverName);
        }

        return sprintf(
            '%s • %s • %s',
            $node->serverName,
            mb_strtoupper($node->protocol),
            mb_strtoupper($node->transport)
        );
    }
}
