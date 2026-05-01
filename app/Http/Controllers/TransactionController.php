<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Http\Request;
use Telegram\Bot\Laravel\Facades\Telegram;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = Transaction::with(['user', 'type'])
            ->whereIsApproved(true)
            ->latest()
            ->get();

        $pendingTransactions = Transaction::with(['user', 'type'])
            ->whereIsApproved(false)
            ->latest()
            ->get();

        $balance = User::query()
            ->select('users.id', 'users.balance')
            ->get()
            ->sum('balance');

        return $this->inertia('Transactions/Index', [
            'balance' => (float) $balance,
            'transactions' => $transactions->map(fn (Transaction $transaction) => $this->transactionData($transaction))->values(),
            'pending_transactions' => $pendingTransactions->map(fn (Transaction $transaction) => $this->transactionData($transaction))->values(),
        ]);
    }

    public function create()
    {
        $users = User::get();
        $types = TransactionType::query()->orderBy('id')->get();

        return $this->inertia('Transactions/Form', [
            'mode' => 'create',
            'submit_url' => route('transactions.store'),
            'transaction' => null,
            'users' => $users->map(fn (User $user) => $this->userData($user))->values(),
            'types' => $types->map(fn (TransactionType $type) => $this->transactionTypeData($type))->values(),
        ]);
    }

    public function store(Request $request)
    {
        Transaction::create($this->validatedData($request));
        return redirect()->route('transactions.index');
    }

    public function edit(Transaction $transaction)
    {
        $users = User::get();
        $types = TransactionType::query()->orderBy('id')->get();

        return $this->inertia('Transactions/Form', [
            'mode' => 'edit',
            'submit_url' => route('transactions.update', $transaction),
            'transaction' => $this->transactionData($transaction),
            'users' => $users->map(fn (User $user) => $this->userData($user))->values(),
            'types' => $types->map(fn (TransactionType $type) => $this->transactionTypeData($type))->values(),
        ]);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $transaction->update($this->validatedData($request));
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

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'type_id' => ['required', 'exists:transaction_types,id'],
            'amount' => ['required', 'numeric'],
            'description' => ['nullable', 'string'],
            'is_approved' => ['nullable', 'boolean'],
        ]);
    }
}
