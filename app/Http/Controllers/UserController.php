<?php

namespace App\Http\Controllers;

use App\Models\CurrentPayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->select([
                'users.*',
                DB::raw('SUM(amount) AS payment_amount')
            ])
            ->withSum('transactions', 'amount')
            ->leftJoin('current_payments', function ($join) {
                $join
                    ->where('start_date', '>=', DB::raw('users.join_at'))
                    ->orWhereNull('join_at');
            })
            ->groupBy('users.id')
            ->get();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $payments = CurrentPayment::select(['start_date', 'amount'])->get();
        return view('users.create', compact('payments'));
    }

    public function store(Request $request)
    {
        User::create($request->post());
        return redirect()->route('users.index');
    }

    public function edit(User $user)
    {
        $payments = CurrentPayment::select(['start_date', 'amount'])->get();
        return view('users.edit', compact('user', 'payments'));
    }

    public function update(Request $request, User $user)
    {
        $user->update($request->post());
        return redirect()->route('users.index');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('users.index');
    }
}
