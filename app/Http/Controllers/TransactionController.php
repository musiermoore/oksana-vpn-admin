<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = Transaction::with('user')->get();
        return view('transactions.index', compact('transactions'));
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
}
