<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Config;
use App\Models\VlessConfig;
use App\Support\WireGuardConfigPublicId;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiWireGuardConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $telegramId = (string) $request->route('telegramId');
        $configId = $this->resolveConfigId();

        return [
            'id' => $configId,
            'name' => $this->resource->name,
            'download_url' => route('api.users.configs.download', [
                'telegramId' => $telegramId,
                'type' => 'wireguard',
                'config' => $configId,
            ], absolute: false),
            'qr_code_url' => route('api.users.configs.qr-code', [
                'telegramId' => $telegramId,
                'type' => 'wireguard',
                'config' => $configId,
            ], absolute: false),
        ];
    }

    private function resolveConfigId(): int|string
    {
        if ($this->resource instanceof Config || $this->resource instanceof VlessConfig) {
            return WireGuardConfigPublicId::encode($this->resource);
        }

        return (string) $this->resource->id;
    }
}
