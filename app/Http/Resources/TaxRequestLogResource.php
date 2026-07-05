<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxRequestLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'preset' => $this->preset,
            'action' => $this->action,
            'method' => $this->method,
            'endpoint' => $this->endpoint,
            'status' => $this->status,
            'response_status' => $this->response_status,
            'error_message' => $this->error_message,
            'request_payload' => $this->request_payload,
            'response_headers' => $this->response_headers,
            'response_body' => $this->response_body,
            'response_json' => $this->response_json,
            'queued_at' => $this->queued_at?->toAtomString(),
            'started_at' => $this->started_at?->toAtomString(),
            'completed_at' => $this->completed_at?->toAtomString(),
            'invoice' => $this->invoice ? [
                'id' => $this->invoice->id,
                'show_url' => route('invoices.show', $this->invoice),
            ] : null,
        ];
    }
}
