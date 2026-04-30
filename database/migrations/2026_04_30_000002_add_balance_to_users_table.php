<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->float('balance')->default(0)->after('extra_payment');
        });

        $approvedTransactions = DB::table('transactions')
            ->select('user_id', DB::raw('SUM(amount) AS amount'))
            ->where('is_approved', true)
            ->groupBy('user_id');

        $paymentPeriodCharges = DB::table('users')
            ->leftJoin('current_payments', function ($join) {
                $join
                    ->where(function ($query) {
                        $query
                            ->whereColumn('current_payments.start_date', '>=', 'users.join_at')
                            ->orWhereNull('users.join_at');
                    })
                    ->whereDate('current_payments.start_date', '<=', now()->toDateString());
            })
            ->select('users.id', DB::raw('SUM(COALESCE(current_payments.amount, 0)) AS amount'))
            ->groupBy('users.id');

        $extraCharges = DB::table('user_extra_payments')
            ->select('user_id', DB::raw('SUM(amount) AS amount'))
            ->groupBy('user_id');

        $balances = DB::table('users')
            ->leftJoinSub($approvedTransactions, 'approved_transactions', 'approved_transactions.user_id', '=', 'users.id')
            ->leftJoinSub($paymentPeriodCharges, 'payment_period_charges', 'payment_period_charges.id', '=', 'users.id')
            ->leftJoinSub($extraCharges, 'extra_charges', 'extra_charges.user_id', '=', 'users.id')
            ->select([
                'users.id',
                DB::raw(
                    'COALESCE(approved_transactions.amount, 0)'
                    . ' - COALESCE(payment_period_charges.amount, 0)'
                    . ' - COALESCE(extra_charges.amount, 0)'
                    . ' - COALESCE(users.extra_payment, 0) AS balance'
                ),
            ])
            ->get();

        foreach ($balances as $row) {
            DB::table('users')
                ->where('id', $row->id)
                ->update(['balance' => $row->balance]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
