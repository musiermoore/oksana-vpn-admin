<?php

namespace App\Http\Controllers\Api;

use App\Services\Payments\YooKassaWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController
{
    public function __construct(
        private readonly YooKassaWebhookService $webhookService,
    ) {}

    public function __invoke(Request $request)
    {
        Log::debug('YouKassa: начало обработки запроса', [$request->all()]);
        $this->webhookService->handle($request->all());
        Log::debug('YouKassa: конец обработки запроса');

        return response()->json([
            'ok' => true,
        ]);
    }
}
