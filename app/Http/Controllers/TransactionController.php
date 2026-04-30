<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Telegram\Bot\Laravel\Facades\Telegram;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = Transaction::with('user')
            ->whereIsApproved(true)
            ->latest()
            ->get();

        $pendingTransactions = Transaction::with('user')
            ->whereIsApproved(false)
            ->latest()
            ->get();

        $userBalance = User::query()
            ->select([
                DB::raw('SUM(current_payments.amount) + users.extra_payment AS payment_amount')
            ])
            ->leftJoin('current_payments', function ($join) {
                $join
                    ->where(function ($query) {
                        $query
                            ->where('start_date', '>=', DB::raw('users.join_at'))
                            ->orWhereNull('join_at');
                    })
                    ->where('start_date', '<=', DB::raw('CURRENT_TIMESTAMP()'));
            })
            ->groupBy('users.id')
            ->pluck('payment_amount')
            ->sum();

        $balance = $transactions->sum('amount') - $userBalance;

        return $this->inertia('Transactions/Index', [
            'balance' => (float) $balance,
            'transactions' => $transactions->map(fn (Transaction $transaction) => $this->transactionData($transaction))->values(),
            'pending_transactions' => $pendingTransactions->map(fn (Transaction $transaction) => $this->transactionData($transaction))->values(),
        ]);
    }

    public function create()
    {
        $users = User::get();

        return $this->inertia('Transactions/Form', [
            'mode' => 'create',
            'submit_url' => route('transactions.store'),
            'transaction' => null,
            'users' => $users->map(fn (User $user) => $this->userData($user))->values(),
        ]);
    }

    public function store(Request $request)
    {
        Transaction::create($request->post());
        return redirect()->route('transactions.index');
    }

    public function edit(Transaction $transaction)
    {
        $users = User::get();

        return $this->inertia('Transactions/Form', [
            'mode' => 'edit',
            'submit_url' => route('transactions.update', $transaction),
            'transaction' => $this->transactionData($transaction),
            'users' => $users->map(fn (User $user) => $this->userData($user))->values(),
        ]);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $transaction->update($request->post());
        return redirect()->route('transactions.index');
    }

    public function destroy(Transaction $transaction)
    {
        $transaction->delete();
        return redirect()->back();
    }

    public function approve(Transaction $transaction)
    {
        $transaction->update(['is_approved' => true]);

        Telegram::sendMessage([
            'chat_id' => $transaction->user->telegram_id,
            'text' => "Баланс пополнен на $transaction->amount"
        ]);

        return redirect()->back();
    }

    public function decline(Transaction $transaction)
    {
        $amount = $transaction->amount;
        $telegramId = $transaction->user->telegram_id;

        $transaction->delete();

        Telegram::sendMessage([
            'chat_id' => $telegramId,
            'text' => "Пополнение баланса на $amount отклонено"
        ]);

        return redirect()->back();
    }
}
