<?php

namespace App\Http\Resources;

use App\Models\Limit;

class LimitResource
{
    public static function make(Limit $limit): array
    {
        return [
            'id' => $limit->id,
            'amount' => (int) $limit->amount,
            'links' => [
                'destroy' => route('limits.destroy', $limit),
            ],
        ];
    }
}
