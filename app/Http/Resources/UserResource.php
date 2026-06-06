<?php

namespace App\Http\Resources;

use App\Models\ShadowsocksConfig;
use App\Models\VlessConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'telegram' => $this->telegram,
            'telegram_id' => $this->telegram_id,
            'description' => $this->description,
            'join_at' => $this->join_at,
            'balance' => (float) ($this->balance ?? 0),
            'is_active' => $this->is_active,
            'full_name' => $this->full_name,
            'approved_transactions_sum_amount' => (float) ($this->approved_transactions_sum_amount ?? 0),
            'payment_amount' => (float) ($this->payment_amount ?? 0),
            'configs' => $this->whenLoaded('configs', fn () => ConfigResource::collection($this->configs)->toArray($request)),
            'xray_configs' => $this->when(
                $this->relationLoaded('vlessConfigs') || $this->relationLoaded('shadowsocksConfigs'),
                fn () => collect($this->relationLoaded('vlessConfigs') ? $this->vlessConfigs->all() : [])
                    ->concat($this->relationLoaded('shadowsocksConfigs') ? $this->shadowsocksConfigs->all() : [])
                    ->map(function (VlessConfig|ShadowsocksConfig $config) use ($request) {
                        $protocol = $config instanceof VlessConfig ? 'vless' : 'shadowsocks';

                        return (new XrayConfigResource($config, $protocol))->toArray($request);
                    })
                    ->sortBy([
                        fn (array $config) => $config['protocol'],
                        fn (array $config) => $config['server']['code'] ?? '',
                        fn (array $config) => $config['name'],
                    ])
                    ->values()
                    ->all()
            ),
            'transactions' => $this->whenLoaded('transactions', fn () => TransactionResource::collection($this->transactions)->toArray($request)),
            'links' => [
                'edit' => route('users.edit', $this->resource),
                'destroy' => route('users.destroy', $this->resource),
            ],
        ];
    }
}
