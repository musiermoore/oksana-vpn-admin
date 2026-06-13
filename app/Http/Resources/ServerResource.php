<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'ip' => $this->ip,
            'type' => $this->type,
            'is_https' => (bool) $this->is_https,
            'link_host' => $this->link_host,
            'panel_link' => $this->panel_link,
            'panel_username' => $this->panel_username,
            'panel_api_version' => $this->getPanelApiVersion(),
            'app_path' => $this->app_path,
            'ssh_public_key' => $this->ssh_public_key,
            'is_vless' => (bool) $this->is_vless,
            'is_ready' => (bool) $this->is_ready,
            'hide_configs_for_non_admins' => (bool) $this->hide_configs_for_non_admins,
            'allowed_inbound_ids' => $this->allowed_inbound_ids,
            'links' => [
                'edit' => route('servers.edit', $this->resource),
                'destroy' => route('servers.destroy', $this->resource),
            ],
        ];
    }
}
