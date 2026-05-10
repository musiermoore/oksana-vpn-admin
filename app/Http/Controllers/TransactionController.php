<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Http\Resources\TransactionTypeResource;
use App\Http\Resources\UserResource;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\Crud\TransactionCrudService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionCrudService $transactionService,
    ) {}

    public function index(Request $request)
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
            'transactions' => TransactionResource::collection($transactions)->toArray($request),
            'pending_transactions' => TransactionResource::collection($pendingTransactions)->toArray($request),
        ]);
    }

    public function create(Request $request)
    {
        $users = User::get();
        $types = TransactionType::query()->orderBy('id')->get();

        return $this->inertia('Transactions/Form', [
            'mode' => 'create',
            'submit_url' => route('transactions.store'),
            'transaction' => null,
            'users' => UserResource::collection($users)->toArray($request),
            'types' => TransactionTypeResource::collection($types)->toArray($request),
        ]);
    }

    public function store(StoreTransactionRequest $request)
    {
        $this->transactionService->create($request->toDto());

        return redirect()->route('transactions.index');
    }

    public function edit(Request $request, Transaction $transaction)
    {
        $users = User::get();
        $types = TransactionType::query()->orderBy('id')->get();

        return $this->inertia('Transactions/Form', [
            'mode' => 'edit',
            'submit_url' => route('transactions.update', $transaction),
            'transaction' => (new TransactionResource($transaction))->toArray($request),
            'users' => UserResource::collection($users)->toArray($request),
            'types' => TransactionTypeResource::collection($types)->toArray($request),
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
