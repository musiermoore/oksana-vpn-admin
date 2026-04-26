<?php

namespace App\Console\Commands;

use App\Models\CurrentPayment;
use App\Models\User;
use Illuminate\Console\Command;

class AddExtraPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:add-extra-payments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add extra payments from the previous month if exist';

    private ?int $currentPaymentPeriodId = null;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->currentPaymentPeriodId = CurrentPayment::getActivePaymentPeriodId();

        $previousPeriodId = CurrentPayment::orderByDesc('start_date')
            ->where('id', '!=', $this->currentPaymentPeriodId)
            ->value('id');

        if (! $this->currentPaymentPeriodId || ! $previousPeriodId) {
            return;
        }

        $users = User::query()
            ->withWhereHas('extraPayments', function ($query) use ($previousPeriodId) {
                $query
                    ->where('current_payment_id', $previousPeriodId)
                    ->where('amount', '>', 0);
            })
            ->whereDoesntHave('extraPayments', function ($query) {
                $query->where('current_payment_id', $this->currentPaymentPeriodId);
            })
            ->get();

        foreach ($users as $user) {
            $this->addExtraPayment($user);
        }
    }

    private function addExtraPayment(User $user): void
    {
        $previousExtraPayment = $user->extraPayments->first();

        if (! $previousExtraPayment) {
            return;
        }

        $user->extraPayments()->create([
            'current_payment_id' => $this->currentPaymentPeriodId,
            'amount' => $previousExtraPayment->amount,
        ]);
    }
}
