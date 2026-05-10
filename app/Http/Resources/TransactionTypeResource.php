<?php

namespace App\Http\Resources;

use App\Models\TransactionType;

class TransactionTypeResource
{
    public static function make(TransactionType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'slug' => $type->slug,
        ];
    }
}
