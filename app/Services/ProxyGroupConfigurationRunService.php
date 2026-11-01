<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\ProxyGroupConfigurationUpdated;
use App\Jobs\RunProxyGroupConfigurationJob;
use App\Models\ProxyGroup;
use App\Models\ProxyGroupConfigurationRun;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProxyGroupConfigurationRunService
{
    /** @return array{run:ProxyGroupConfigurationRun,started:bool} */
    public function start(ProxyGroup $group, int $userId): array
    {
        [$run, $started] = DB::transaction(function () use ($group, $userId): array {
            $lockedGroup = ProxyGroup::query()->lockForUpdate()->findOrFail($group->id);
            $activeRun = ProxyGroupConfigurationRun::query()
                ->where('proxy_group_id', $lockedGroup->id)
                ->whereIn('status', [
                    ProxyGroupConfigurationRun::STATUS_PENDING,
                    ProxyGroupConfigurationRun::STATUS_RUNNING,
                ])
                ->latest('id')
                ->first();

            if ($activeRun instanceof ProxyGroupConfigurationRun) {
                return [$activeRun, false];
            }

            $run = ProxyGroupConfigurationRun::query()->create([
                'proxy_group_id' => $lockedGroup->id,
                'started_by' => $userId,
                'group_name' => $lockedGroup->name,
                'status' => ProxyGroupConfigurationRun::STATUS_PENDING,
                'revision' => 0,
                'steps' => [],
            ]);

            return [$run, true];
        });

        if ($started) {
            try {
                RunProxyGroupConfigurationJob::dispatch((int) $run->id);
            } catch (Throwable $exception) {
                report($exception);
                $this->appendStep($run, [
                    'server' => 'Приложение',
                'step' => 'Добавление задачи в очередь default',
                    'success' => false,
                    'message' => $exception->getMessage(),
                ]);
                $this->setStatus($run, ProxyGroupConfigurationRun::STATUS_FAILED, $exception->getMessage());
            }
            $this->broadcast($run->refresh());
        }

        return ['run' => $run, 'started' => $started];
    }

    public function latestForGroup(ProxyGroup $group): ?ProxyGroupConfigurationRun
    {
        return ProxyGroupConfigurationRun::query()
            ->where('proxy_group_id', $group->id)
            ->latest('id')
            ->first();
    }

    /** @param array{server:string,step:string,success:bool,message:string} $step */
    public function appendStep(ProxyGroupConfigurationRun $run, array $step): void
    {
        $run->refresh();
        $steps = $run->steps ?? [];
        $steps[] = $step;
        $run->forceFill(['steps' => $steps, 'revision' => (int) $run->revision + 1])->save();
        $this->broadcast($run);
    }

    public function setStatus(ProxyGroupConfigurationRun $run, string $status, ?string $errorMessage = null): void
    {
        $attributes = [
            'status' => $status,
            'error_message' => $errorMessage,
            'revision' => (int) $run->revision + 1,
        ];

        if ($status === ProxyGroupConfigurationRun::STATUS_RUNNING) {
            $attributes['started_at'] = now();
        }

        if (in_array($status, [ProxyGroupConfigurationRun::STATUS_COMPLETED, ProxyGroupConfigurationRun::STATUS_FAILED], true)) {
            $attributes['finished_at'] = now();
        }

        $run->forceFill($attributes)->save();
        $this->broadcast($run);
    }

    private function broadcast(ProxyGroupConfigurationRun $run): void
    {
        try {
            broadcast(new ProxyGroupConfigurationUpdated($run));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
