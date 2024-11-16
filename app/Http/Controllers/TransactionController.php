<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
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

        return view('transactions.index', compact(
            'transactions',
            'pendingTransactions'
        ));
    }

    public function create()
    {
        $users = User::get();

        return view('transactions.create', compact('users'));
    }

    public function store(Request $request)
    {
        Transaction::create($request->post());
        return redirect()->route('transactions.index');
    }

    public function edit(Transaction $transaction)
    {
        $users = User::get();

        return view('transactions.edit', compact('transaction', 'users'));
    }

    public function update(Request $request, Transaction $transaction)
    {
        $transaction->update($request->post());
        return redirect()->route('transactions.index');
    }

    public function destroy(Transaction $transaction)
    {
        $transaction->delete();
        return redirect()->route('transactions.index');
    }

    public function approve(Transaction $transaction)
    {
        $transaction->update(['is_approved' => true]);

        Telegram::sendMessage([
            'chat_id' => $transaction->user->telegram_id,
            'text' => "Баланс пополнен на $transaction->amount"
        ]);

        return redirect()->route('transactions.index');
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

        return redirect()->route('transactions.index');
    }
}
