<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table('transaction_types')->updateOrInsert(
            ['slug' => 'extra-payment'],
            [
                'name' => 'Доп. оплата',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $subscriptionTypeId = DB::table('transaction_types')
            ->where('slug', 'subscription')
            ->value('id');
        $extraPaymentTypeId = DB::table('transaction_types')
            ->where('slug', 'extra-payment')
            ->value('id');

        if ($subscriptionTypeId) {
            DB::table('transactions')->insertUsing(
                ['user_id', 'type_id', 'amount', 'is_approved', 'description', 'created_at', 'updated_at'],
                DB::table('users')
                    ->join('current_payments', function ($join) {
                        $join->on('current_payments.start_date', '>=', 'users.join_at');
                    })
                    ->whereNotNull('users.join_at')
                    ->where('current_payments.start_date', '<=', $now->toDateString())
                    ->whereNotExists(function ($query) use ($subscriptionTypeId) {
                        $query
                            ->select(DB::raw(1))
                            ->from('transactions')
                            ->whereColumn('transactions.user_id', 'users.id')
                            ->where('transactions.type_id', $subscriptionTypeId)
                            ->whereRaw(
                                "transactions.description = CONCAT('Начисление подписки за период ', current_payments.start_date, ' - ', current_payments.end_date)"
                            );
                    })
                    ->whereNotExists(function ($query) use ($subscriptionTypeId) {
                        $query
                            ->select(DB::raw(1))
                            ->from('transactions')
                            ->whereColumn('transactions.user_id', 'users.id')
                            ->where('transactions.type_id', $subscriptionTypeId)
                            ->where('transactions.is_approved', true)
                            ->whereRaw('transactions.amount = -current_payments.amount')
                            ->whereRaw('DATE(transactions.created_at) BETWEEN current_payments.start_date AND current_payments.end_date');
                    })
                    ->selectRaw(
                        "users.id,
                        ?,
                        -current_payments.amount,
                        1,
                        CONCAT('Начисление подписки за период ', current_payments.start_date, ' - ', current_payments.end_date),
                        CONCAT(current_payments.start_date, ' 00:00:00'),
                        CONCAT(current_payments.start_date, ' 00:00:00')",
                        [$subscriptionTypeId]
                    )
            );
        }

        if ($extraPaymentTypeId) {
            DB::table('transactions')->insertUsing(
                ['user_id', 'type_id', 'amount', 'is_approved', 'description', 'created_at', 'updated_at'],
                DB::table('user_extra_payments')
                    ->where('user_extra_payments.amount', '>', 0)
                    ->whereNotExists(function ($query) use ($extraPaymentTypeId) {
                        $query
                            ->select(DB::raw(1))
                            ->from('transactions')
                            ->where('transactions.type_id', $extraPaymentTypeId)
                            ->whereRaw(
                                "transactions.description = CONCAT('Доп. оплата по записи #', user_extra_payments.id)"
                            );
                    })
                    ->selectRaw(
                        "user_extra_payments.user_id,
                        ?,
                        -user_extra_payments.amount,
                        1,
                        CONCAT('Доп. оплата по записи #', user_extra_payments.id),
                        user_extra_payments.created_at,
                        user_extra_payments.updated_at",
                        [$extraPaymentTypeId]
                    )
            );

            DB::table('transactions')->insertUsing(
                ['user_id', 'type_id', 'amount', 'is_approved', 'description', 'created_at', 'updated_at'],
                DB::table('users')
                    ->where('users.extra_payment', '>', 0)
                    ->whereNotExists(function ($query) use ($extraPaymentTypeId) {
                        $query
                            ->select(DB::raw(1))
                            ->from('transactions')
                            ->whereColumn('transactions.user_id', 'users.id')
                            ->where('transactions.type_id', $extraPaymentTypeId)
                            ->where('transactions.description', 'Доп. оплата из карточки пользователя');
                    })
                    ->selectRaw(
                        "users.id,
                        ?,
                        -users.extra_payment,
                        1,
                        'Доп. оплата из карточки пользователя',
                        users.created_at,
                        users.updated_at",
                        [$extraPaymentTypeId]
                    )
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $subscriptionTypeId = DB::table('transaction_types')
            ->where('slug', 'subscription')
            ->value('id');
        $extraPaymentTypeId = DB::table('transaction_types')
            ->where('slug', 'extra-payment')
            ->value('id');

        if ($subscriptionTypeId) {
            DB::table('transactions')
                ->where('type_id', $subscriptionTypeId)
                ->where('description', 'like', 'Начисление подписки за период %')
                ->delete();
        }

        if ($extraPaymentTypeId) {
            DB::table('transactions')
                ->where('type_id', $extraPaymentTypeId)
                ->where(function ($query) {
                    $query
                        ->where('description', 'like', 'Доп. оплата по записи #%')
                        ->orWhere('description', '=', 'Доп. оплата из карточки пользователя');
                })
                ->delete();
        }
    }
};
