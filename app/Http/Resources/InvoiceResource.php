<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'provider_payment_id' => $this->provider_payment_id,
            'status' => $this->status,
            'paid' => (bool) $this->paid,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'description' => $this->description,
            'confirmation_url' => $this->confirmation_url,
            'paid_at' => $this->paid_at?->toAtomString(),
            'created_at' => $this->created_at?->toAtomString(),
            'tax_status' => $this->tax_status,
            'tax_sent_at' => $this->tax_sent_at?->toAtomString(),
            'tax_queued_at' => $this->tax_queued_at?->toAtomString(),
            'tax_last_error_at' => $this->tax_last_error_at?->toAtomString(),
            'tax_receipt_uuid' => $this->tax_receipt_uuid,
            'tax_service_name' => $this->tax_service_name,
            'tax_estimated_commission' => $this->tax_estimated_commission !== null
                ? (float) $this->tax_estimated_commission
                : round((float) $this->amount * 0.04, 2),
            'tax_error_message' => $this->tax_error_message,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'full_name' => $this->user->full_name,
                'edit_url' => route('users.edit', $this->user),
            ] : null,
            'transactions' => $this->relationLoaded('transactions')
                ? TransactionResource::collection($this->transactions)->toArray($request)
                : [],
            'links' => [
                'show' => route('invoices.show', $this->resource),
                'send_preview' => route('invoices.send-preview', $this->resource),
                'send' => route('invoices.send', $this->resource),
            ],
        ];
    }
}
