<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\PaymentWebhookLog;
use App\Services\Payments\PaymentWebhookLogService;
use App\Services\Payments\PaymentWebhookProcessor;

class PaymentWebhookLogController
{
    public function __construct(
        private readonly PaymentWebhookLogService $logs,
        private readonly PaymentWebhookProcessor $processor,
    ) {}

    public function replay(PaymentWebhookLog $paymentWebhookLog)
    {
        $replayLog = $this->logs->createReplay($paymentWebhookLog);
        $response = $this->processor->process($replayLog);

        return response()->json([
            ...$response,
            'replay_log_id' => $replayLog->id,
            'source_log_id' => $paymentWebhookLog->id,
        ]);
    }
}
