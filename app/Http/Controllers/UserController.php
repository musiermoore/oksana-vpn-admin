<?php

namespace App\Http\Controllers;

use App\Models\CurrentPayment;
use App\Models\User;
use App\Models\UserToken;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $getAll = $request->boolean('all');
        $onlyInactive = $request->boolean('inactive');

        $users = User::query()
            ->select('users.*')
            ->tap(fn ($query) => User::applyBillingSummary($query))
            ->when(!$onlyInactive && ! $getAll, fn ($query) => $query->where('users.is_active', '=', true))
            ->when($onlyInactive, fn ($query) => $query->where('users.is_active', '=', false))
            ->orderByRaw('GREATEST(0, -IFNULL(users.balance, 0)) DESC')
            ->orderBy('created_at')
            ->get();

        return $this->inertia('Users/Index', [
            'filters' => [
                'all' => $getAll,
                'inactive' => $onlyInactive,
            ],
            'users' => $users->map(fn (User $user) => $this->userData($user))->values(),
        ]);
    }

    public function create()
    {
        $payments = CurrentPayment::select(['id', 'start_date', 'end_date', 'amount'])
            ->orderByDesc('start_date')
            ->get();

        return $this->inertia('Users/Form', [
            'mode' => 'create',
            'submit_url' => route('users.store'),
            'user' => null,
            'payments' => $payments->map(fn ($payment) => $this->currentPaymentData($payment))->values(),
        ]);
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
        $payments = CurrentPayment::select(['id', 'start_date', 'end_date', 'amount'])
            ->orderByDesc('start_date')
            ->get();

        $user->load([
            'transactions' => function ($query) {
                $query->latest();
            },
            'configs'
        ]);

        return $this->inertia('Users/Form', [
            'mode' => 'edit',
            'submit_url' => route('users.update', $user),
            'user' => $this->userData($user, true),
            'payments' => $payments->map(fn ($payment) => $this->currentPaymentData($payment))->values(),
        ]);
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

        return $this->inertia('Users/Configs', [
            'token' => [
                ...$this->userTokenData($userToken),
                'download_items' => $userToken->user->configs->map(function ($config) use ($request, $userToken) {
                    $params = [
                        'userToken' => $userToken->token,
                        'config' => $config->id,
                        'password' => $request->password,
                    ];

                    return [
                        'id' => $config->id,
                        'name' => $config->name,
                        'qr_code_url' => route('users.configs.qr-code', $params),
                        'download_url' => route('users.configs.download', $params),
                    ];
                })->values(),
            ],
            'is_password_correct' => $isPasswordCorrect,
            'password' => $request->password,
        ]);
    }
}
