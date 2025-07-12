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

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $currentPaymentPeriodId = CurrentPayment::orderByDesc('start_date')->value('id');

        $previousPeriodId = CurrentPayment::orderByDesc('start_date')
            ->where('id', '!=', $currentPaymentPeriodId)
            ->value('id');

        $usersWithExtraPayments = User::query()
            ->withWhereHas('extraPayments', function ($query) use ($previousPeriodId) {
                $query->where('current_payment_id', '=', $previousPeriodId);
            })
            ->select(['users.*'])
            ->doesntHave('extraPayments', callback: function ($query) use ($currentPaymentPeriodId) {
                $query->where('current_payment_id', '=', $currentPaymentPeriodId);
            })
            ->get();

        foreach ($usersWithExtraPayments as $user) {
            $previousExtraPayment = $user->extraPayments->first();

            if (empty($previousExtraPayment)) {
                continue;
            }

            $user->extraPayments()->create([
                'current_payment_id' => $currentPaymentPeriodId,
                'amount' => $previousExtraPayment->amount
            ]);
        }
    }
}
