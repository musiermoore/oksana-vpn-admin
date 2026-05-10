<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsInertiaData;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\Crud\TransactionCrudService;

class TransactionController extends Controller
{
    use BuildsInertiaData;

    public function __construct(
        private readonly TransactionCrudService $transactionService,
    ) {}

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

    public function store(StoreTransactionRequest $request)
    {
        $this->transactionService->create($request->toDto());

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

    public function update(UpdateTransactionRequest $request, Transaction $transaction)
    {
        $this->transactionService->update($transaction, $request->toDto());

        return redirect()->route('transactions.index');
    }

    public function destroy(Transaction $transaction)
    {
        $this->transactionService->delete($transaction);

        return redirect()->back();
    }

    public function approve(Transaction $transaction)
    {
        $this->transactionService->approve($transaction);

        return redirect()->back();
    }

    public function decline(Transaction $transaction)
    {
        $this->transactionService->decline($transaction);

        return redirect()->back();
    }
}
