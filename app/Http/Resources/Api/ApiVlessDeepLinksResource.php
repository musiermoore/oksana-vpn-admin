<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiVlessDeepLinksResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'link' => $this['link'],
            'raw_link' => $this['raw_link'] ?? $this['link'],
            'show_raw_link' => (bool) ($this['show_raw_link'] ?? true),
            'happ_deep_link' => $this['happ_deep_link'],
            'v2rayn_deeplink' => $this['v2rayn_deeplink'],
            'v2rayng_deeplink' => $this['v2rayng_deeplink'],
            'v2raybox_deeplink' => $this['v2raybox_deeplink'],
            'sing_box_deeplink' => $this['sing_box_deeplink'],
            'hiddify_deeplink' => $this['hiddify_deeplink'],
            'v2raytun_deeplink' => $this['v2raytun_deeplink'],
            'incy_deeplink' => $this['incy_deeplink'],
        ];
    }
}
