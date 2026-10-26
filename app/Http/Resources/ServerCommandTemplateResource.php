<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerCommandTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'description' => $this->description,
            'format' => (string) $this->format,
            'content' => (string) $this->content,
            'timeout_seconds' => (int) $this->timeout_seconds,
            'links' => [
                'edit' => route('server-command-templates.edit', $this->resource),
                'destroy' => route('server-command-templates.destroy', $this->resource),
            ],
        ];
    }
}
