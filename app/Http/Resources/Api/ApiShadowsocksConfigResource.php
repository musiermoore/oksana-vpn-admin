<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiShadowsocksConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $telegramId = (string) $request->route('telegramId');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'download_url' => route('api.users.configs.download', [
                'telegramId' => $telegramId,
                'type' => 'shadowsocks',
                'config' => $this->id,
            ], absolute: false),
            'qr_code_url' => route('api.users.configs.qr-code', [
                'telegramId' => $telegramId,
                'type' => 'shadowsocks',
                'config' => $this->id,
            ], absolute: false),
        ];
    }
}
