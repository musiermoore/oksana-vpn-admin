<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\Payments\PaymentWebhookLogService;
use App\Services\Payments\PaymentWebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController
{
    public function __construct(
        private readonly PaymentWebhookLogService $logs,
        private readonly PaymentWebhookProcessor $processor,
    ) {}

    public function __invoke(Request $request)
    {
        Log::debug('YouKassa: начало обработки запроса', [$request->all()]);
        $log = $this->logs->createForRequest('yookassa', $request);
        $response = $this->processor->process($log);
        Log::debug('YouKassa: конец обработки запроса', ['payment_webhook_log_id' => $log->id]);

        return response()->json($response);
    }
}
