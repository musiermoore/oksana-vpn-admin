<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ConfigGroupResource extends JsonResource
{
    /** @return array{id:int,name:string,sort:int,is_default:bool} */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'sort' => (int) $this->sort,
            'is_default' => (bool) $this->is_default,
        ];
    }
}
