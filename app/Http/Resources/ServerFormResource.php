<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ServerFormResource extends ServerResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'panel_password' => $this->panel_password,
            'prices' => $this->whenLoaded('prices', fn () => $this->prices
                ->map(fn ($price) => [
                    'id' => (int) $price->id,
                    'effective_from' => $price->effective_from?->format('Y-m-d'),
                    'price' => (float) $price->price,
                ])
                ->values()
                ->all(), []),
            'inbounds' => $this->whenLoaded('xrayInbounds', fn () => $this->xrayInbounds
                ->sortBy([
                    fn ($inbound) => (int) $inbound->sort_order,
                    fn ($inbound) => (int) $inbound->external_id,
                    fn ($inbound) => (int) $inbound->id,
                ])
                ->map(fn ($inbound) => [
                    'id' => (int) $inbound->id,
                    'external_id' => (int) $inbound->external_id,
                    'sort_order' => (int) $inbound->sort_order,
                    'is_active' => (bool) $inbound->is_active,
                    'is_public' => (bool) $inbound->is_public,
                    'protocol' => (string) data_get($inbound->params, 'protocol', ''),
                    'remark' => (string) data_get($inbound->params, 'remark', ''),
                ])
                ->values()
                ->all(), []),
            'connect_items' => $this->when(
                $this->relationLoaded('xrayInbounds') && $this->relationLoaded('proxies'),
                fn () => $this->buildConnectItems(),
                [],
            ),
        ];
    }

    /**
     * @return array<int, array<string, int|string|null>>
     */
    private function buildConnectItems(): array
    {
        $inboundItems = $this->xrayInbounds->map(fn ($inbound) => [
            'key' => 'inbound:'.$inbound->id,
            'type' => 'inbound',
            'entity_id' => (int) $inbound->id,
            'sort_order' => (int) $inbound->sort_order,
            'title' => 'Inbound #'.(int) $inbound->external_id,
            'subtitle' => trim(implode(' · ', array_filter([
                (string) data_get($inbound->params, 'protocol', ''),
                (string) data_get($inbound->params, 'remark', ''),
            ]))) ?: null,
        ]);

        $proxyItems = $this->proxies->map(fn ($proxy) => [
            'key' => 'proxy:'.$proxy->id,
            'type' => 'proxy',
            'entity_id' => (int) $proxy->id,
            'sort_order' => (int) $proxy->sort_order,
            'title' => 'Proxy '.$proxy->name,
            'subtitle' => trim(implode(' · ', array_filter([
                trim((string) $proxy->host) !== '' ? $proxy->host.':'.$proxy->port : '',
                $proxy->inbound_id ? 'Inbound #'.$proxy->inbound_id : 'Все inbound',
            ]))) ?: null,
        ]);

        return $inboundItems
            ->concat($proxyItems)
            ->sortBy([
                fn (array $item) => (int) $item['sort_order'],
                fn (array $item) => (string) $item['type'],
                fn (array $item) => (int) $item['entity_id'],
            ])
            ->values()
            ->all();
    }
}
