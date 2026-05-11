<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->float('current_balance_amount')->nullable()->after('amount');
        });

        $users = DB::table('users')
            ->select(['id'])
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            $transactions = DB::table('transactions')
                ->where('user_id', $user->id)
                ->where('is_approved', true)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'amount']);

            $runningBalance = 0.0;

            foreach ($transactions as $transaction) {
                $runningBalance += (float) $transaction->amount;

                DB::table('transactions')
                    ->where('id', $transaction->id)
                    ->update(['current_balance_amount' => $runningBalance]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('current_balance_amount');
        });
    }
};
