<?php

namespace App\Http\Controllers;

use App\Models\CurrentPayment;
use App\Models\User;
use App\Models\UserToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $getAll = $request->boolean('all');
        $onlyInactive = $request->boolean('inactive');

        $users = User::query()
            ->select([
                'users.*',
                DB::raw('SUM(amount) + users.extra_payment AS payment_amount')
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
            ->when(!$onlyInactive && ! $getAll, fn ($query) => $query->where('users.is_active', '=', true))
            ->when($onlyInactive, fn ($query) => $query->where('users.is_active', '=', false))
            ->groupBy('users.id')
            ->orderByRaw('GREATEST(0, IFNULL(payment_amount, 0) - IFNULL(transactions_sum_amount, 0)) DESC')
            ->orderBy('created_at')
            ->get();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $payments = CurrentPayment::select(['start_date', 'amount'])
            ->orderByDesc('start_date')
            ->get();

        return view('users.create', compact('payments'));
    }

    public function store(Request $request)
    {
        $user = User::create($request->post());

        if ($request->boolean('create_configs')) {
            $user->createDefaultConfigs();
        }

        return redirect()->route('users.index');
    }

    public function edit(User $user)
    {
        $payments = CurrentPayment::select(['start_date', 'amount'])
            ->orderByDesc('start_date')
            ->get();

        $user->load([
            'transactions' => function ($query) {
                $query->latest();
            },
            'configs'
        ]);

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

    public function configs(Request $request, UserToken $userToken)
    {
        $isPasswordCorrect = $userToken->validateToken($request->password);

        if ($isPasswordCorrect && empty($userToken->expires_at)) {
            $userToken->update([
                'expires_at' => now()->addMinutes(10)
            ]);
        }

        return view('users.configs', compact('userToken', 'isPasswordCorrect'));
    }
}
