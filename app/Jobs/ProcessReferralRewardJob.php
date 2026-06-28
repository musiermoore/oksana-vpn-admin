<?php

namespace App\Jobs;

use App\Models\Referral;
use App\Services\ReferralRewardService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessReferralRewardJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $referralId,
    ) {
        $this->onQueue('payments');
    }

    public function handle(ReferralRewardService $referralRewards): void
    {
        $referral = Referral::query()->find($this->referralId);

        if (! $referral) {
            return;
        }

        $referralRewards->processReward($referral);
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
