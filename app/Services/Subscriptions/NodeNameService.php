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
                $baseName = sprintf(
                    '%s • %s • %s',
                    $node->serverName,
                    mb_strtoupper($node->protocol),
                    mb_strtoupper($node->transport)
                );

                $names[$node->id] = $needsNumbering
                    ? $baseName.' #'.($index + 1)
                    : $baseName;
            }
        }

        return $names;
    }

    private function groupingKey(NormalizedNode $node): string
    {
        return implode('|', [
            mb_strtolower($node->serverName),
            mb_strtolower($node->protocol),
            mb_strtolower($node->transport),
        ]);
    }
}
