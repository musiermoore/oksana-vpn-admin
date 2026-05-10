<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiRequestLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'method' => $this->method,
            'endpoint' => $this->endpoint,
            'params' => $this->params,
            'request_timezone' => $this->request_timezone,
            'request_timezone_offset' => $this->request_timezone_offset,
            'response_status' => $this->response_status,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toISOString(),
            'formatted_created_at' => $this->created_at?->format('d.m.Y H:i:s'),
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'telegram' => $this->user->telegram,
                'full_name' => $this->user->full_name,
                'edit_url' => route('users.edit', $this->user),
            ] : null,
        ];
    }
}
