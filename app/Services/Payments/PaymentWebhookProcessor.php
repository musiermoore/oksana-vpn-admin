<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\PaymentWebhookLog;
use Throwable;

class PaymentWebhookProcessor
{
    public function __construct(
        private readonly YooKassaWebhookService $yooKassaWebhookService,
        private readonly PaymentWebhookLogService $logs,
    ) {}

    /**
     * @return array{ok: bool}
     */
    public function process(PaymentWebhookLog $log): array
    {
        try {
            $this->yooKassaWebhookService->handle($log->request_payload ?? []);
        } catch (Throwable $exception) {
            $this->logs->markFailed($log, $exception);

            throw $exception;
        }

        return $this->logs->markProcessed($log, [
            'ok' => true,
        ])->response_payload ?? ['ok' => true];
    }
}
