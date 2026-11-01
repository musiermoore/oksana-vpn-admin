<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ProxyGroup;
use App\Models\ProxyGroupConfigurationRun;
use App\Services\ProxyGroupConfigurationRunService;
use App\Services\ProxyGroupConfigurationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RunProxyGroupConfigurationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public readonly int $runId)
    {
        $this->onConnection('redis');
        $this->onQueue('default');
    }

    public function handle(
        ProxyGroupConfigurationService $configuration,
        ProxyGroupConfigurationRunService $runs,
    ): void {
        $run = ProxyGroupConfigurationRun::query()->find($this->runId);
        if (! $run instanceof ProxyGroupConfigurationRun) {
            return;
        }

        $lock = Cache::lock('proxy-group-configuration:'.$run->proxy_group_id, 960);
        if (! $lock->get()) {
            $runs->appendStep($run, [
                'server' => 'Приложение',
                'step' => 'Ожидание блокировки',
                'success' => false,
                'message' => 'Для этой группы уже выполняется другая настройка HAProxy.',
            ]);
            $runs->setStatus($run, ProxyGroupConfigurationRun::STATUS_FAILED, 'Concurrent deployment blocked.');

            return;
        }

        try {
            $run->refresh();
            $runs->setStatus($run, ProxyGroupConfigurationRun::STATUS_RUNNING);
            $group = ProxyGroup::query()->find($run->proxy_group_id);
            if (! $group instanceof ProxyGroup) {
                throw new \RuntimeException('The proxy group was deleted before configuration started.');
            }

            $result = $configuration->apply(
                $group,
                fn (array $step): mixed => $runs->appendStep($run, $step),
            );

            $status = $result['success']
                ? ProxyGroupConfigurationRun::STATUS_COMPLETED
                : ProxyGroupConfigurationRun::STATUS_FAILED;
            $runs->setStatus($run, $status);
        } catch (Throwable $exception) {
            report($exception);
            $runs->appendStep($run, [
                'server' => 'Приложение',
                'step' => 'Ошибка выполнения',
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
            $runs->setStatus($run, ProxyGroupConfigurationRun::STATUS_FAILED, $exception->getMessage());
        } finally {
            $lock->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
        $run = ProxyGroupConfigurationRun::query()->find($this->runId);
        if (! $run instanceof ProxyGroupConfigurationRun || in_array($run->status, [
            ProxyGroupConfigurationRun::STATUS_COMPLETED,
            ProxyGroupConfigurationRun::STATUS_FAILED,
        ], true)) {
            return;
        }

        $runs = app(ProxyGroupConfigurationRunService::class);
        $runs->appendStep($run, [
            'server' => 'Приложение',
            'step' => 'Задача завершилась аварийно',
            'success' => false,
            'message' => $exception->getMessage(),
        ]);
        $runs->setStatus($run, ProxyGroupConfigurationRun::STATUS_FAILED, $exception->getMessage());
    }
}
