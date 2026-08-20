<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\PaymentWebhookLog;
use Illuminate\Http\Request;
use Throwable;

class PaymentWebhookLogService
{
    public function createForRequest(string $provider, Request $request): PaymentWebhookLog
    {
        $payload = $request->all();

        return PaymentWebhookLog::query()->create([
            'provider' => $provider,
            'source' => PaymentWebhookLog::SOURCE_EXTERNAL,
            'event' => $this->extractEvent($payload),
            'provider_payment_id' => $this->extractPaymentId($payload),
            'request_method' => $request->getMethod(),
            'request_url' => $request->fullUrl(),
            'request_ip' => $request->ip(),
            'request_user_agent' => $request->userAgent(),
            'request_headers' => $request->headers->all(),
            'request_payload' => $payload,
            ...$this->resolveRelations($payload),
        ]);
    }

    public function createReplay(PaymentWebhookLog $sourceLog): PaymentWebhookLog
    {
        $payload = $sourceLog->request_payload ?? [];

        return PaymentWebhookLog::query()->create([
            'provider' => $sourceLog->provider,
            'source' => PaymentWebhookLog::SOURCE_REPLAY,
            'replayed_from_log_id' => $sourceLog->id,
            'event' => $this->extractEvent($payload),
            'provider_payment_id' => $this->extractPaymentId($payload),
            'request_method' => 'POST',
            'request_url' => route('api.payment.webhook', absolute: false),
            'request_payload' => $payload,
            ...$this->resolveRelations($payload),
        ]);
    }

    /**
     * @param  array<string, mixed>  $responsePayload
     */
    public function markProcessed(PaymentWebhookLog $log, array $responsePayload, int $responseStatus = 200): PaymentWebhookLog
    {
        $log->update([
            'status' => PaymentWebhookLog::STATUS_PROCESSED,
            'response_status' => $responseStatus,
            'response_payload' => $responsePayload,
            'error_message' => null,
            'processed_at' => now(),
            ...$this->resolveRelations($log->request_payload ?? []),
        ]);

        return $log->refresh();
    }

    public function markFailed(PaymentWebhookLog $log, Throwable $exception, ?int $responseStatus = null): PaymentWebhookLog
    {
        $log->update([
            'status' => PaymentWebhookLog::STATUS_FAILED,
            'response_status' => $responseStatus,
            'error_message' => $exception->getMessage(),
            'processed_at' => now(),
            ...$this->resolveRelations($log->request_payload ?? []),
        ]);

        return $log->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{user_id:int|null, invoice_id:int|null, transaction_id:int|null}
     */
    private function resolveRelations(array $payload): array
    {
        $paymentId = $this->extractPaymentId($payload);

        if ($paymentId === null) {
            return [
                'user_id' => null,
                'invoice_id' => null,
                'transaction_id' => null,
            ];
        }

        $invoice = Invoice::query()
            ->with('transactions')
            ->where('provider', 'yookassa')
            ->where('provider_payment_id', $paymentId)
            ->first();

        return [
            'user_id' => $invoice?->user_id,
            'invoice_id' => $invoice?->id,
            'transaction_id' => $invoice?->transactions->first()?->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractEvent(array $payload): ?string
    {
        $event = data_get($payload, 'event');

        return is_string($event) && $event !== '' ? $event : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractPaymentId(array $payload): ?string
    {
        $paymentId = data_get($payload, 'object.id');

        return is_string($paymentId) && $paymentId !== '' ? $paymentId : null;
    }
}
