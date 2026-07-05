<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\DTOs\Tax\TaxDebugRequestData;
use App\Jobs\ExecuteTaxDebugRequestJob;
use App\Models\TaxRequestLog;
use App\Repositories\TaxRequestLogRepository;

class TaxDebugService
{
    public function __construct(
        private readonly TaxRequestLogRepository $logs,
    ) {}

    public function queue(TaxDebugRequestData $data): TaxRequestLog
    {
        $presetConfig = $this->presetConfig($data->preset);

        $log = $this->logs->create([
            'user_id' => $data->userId,
            'invoice_id' => $data->invoiceId,
            'preset' => $data->preset,
            'action' => $presetConfig['action'],
            'method' => $presetConfig['method'],
            'endpoint' => $presetConfig['endpoint'],
            'status' => 'queued',
            'request_payload' => [
                'invoice_id' => $data->invoiceId,
            ],
            'queued_at' => now(),
        ]);

        ExecuteTaxDebugRequestJob::dispatch($log->id);

        return $log;
    }

    /**
     * @return array{action: string, method: string, endpoint: string}
     */
    public function presetConfig(string $preset): array
    {
        return match ($preset) {
            'auth' => ['action' => 'Auth by INN/password', 'method' => 'POST', 'endpoint' => '/auth/lkfl'],
            'user' => ['action' => 'Get current user', 'method' => 'GET', 'endpoint' => '/user'],
            'income' => ['action' => 'Create income receipt - individual', 'method' => 'POST', 'endpoint' => '/income'],
            default => ['action' => 'Unknown', 'method' => 'GET', 'endpoint' => '/'],
        };
    }
}
