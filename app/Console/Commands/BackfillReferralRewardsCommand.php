<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReferralRewardService;
use Illuminate\Console\Command;

class BackfillReferralRewardsCommand extends Command
{
    protected $signature = 'referrals:backfill-rewards {referral_id?}';

    protected $description = 'Backfill pending referral rewards from already approved purchases';

    public function handle(ReferralRewardService $referralRewards): int
    {
        $referralId = $this->argument('referral_id');
        $stats = $referralRewards->backfillPendingRewards(
            $referralId !== null ? (int) $referralId : null
        );

        $this->info('Referral reward backfill completed.');
        $this->line("Processed: {$stats['processed']}");
        $this->line("Rewarded: {$stats['rewarded']}");
        $this->line("Scheduled: {$stats['scheduled']}");
        $this->line("Pending without purchase: {$stats['pending_without_purchase']}");
        $this->line("Skipped missing user: {$stats['skipped_missing_user']}");

        return self::SUCCESS;
    }
}
