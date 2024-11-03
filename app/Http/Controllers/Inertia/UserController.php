<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\CurrentPayment;
use App\Models\User;
use App\Models\UserToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

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
                    ->where(function ($query) {
                        $query
                            ->where('start_date', '>=', DB::raw('users.join_at'))
                            ->orWhereNull('join_at');
                    })
                    ->where('start_date', '<=', DB::raw('CURRENT_TIMESTAMP()'));
            })
            ->groupBy('users.id')
            ->get();

        return Inertia::render('Users/List', compact(
            'users'
        ));
    }

    public function create()
    {
        $payments = CurrentPayment::select(['start_date', 'amount'])->get();
        return Inertia::render('Users/Create', compact(
            'payments'
        ));
    }

    public function store(Request $request)
    {
        User::create($request->post());
        return redirect()->route('users.index');
    }

    public function edit(User $user)
    {
        $payments = CurrentPayment::select(['start_date', 'amount'])->get();
        return Inertia::render('Users/Create', compact(
            'user', 'payments'
        ));
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
