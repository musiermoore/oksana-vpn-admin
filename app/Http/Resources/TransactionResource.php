<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'is_approved' => (bool) $this->is_approved,
            'description' => $this->description,
            'formatted_created_at' => $this->formatted_created_at,
            'type' => $this->type ? (new TransactionTypeResource($this->type))->toArray($request) : null,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'full_name' => $this->user->full_name,
                'is_active' => $this->user->is_active,
                'edit_url' => route('users.edit', $this->user),
            ] : null,
            'links' => [
                'edit' => route('transactions.edit', $this->resource),
                'destroy' => route('transactions.destroy', $this->resource),
                'approve' => route('transactions.approve', $this->resource),
                'decline' => route('transactions.decline', $this->resource),
            ],
        ];
    }
}
