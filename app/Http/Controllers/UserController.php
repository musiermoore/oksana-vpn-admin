<?php

namespace App\Http\Controllers;

use App\Http\Resources\CurrentPaymentResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserTokenResource;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\CurrentPayment;
use App\Models\User;
use App\Models\UserToken;
use App\Services\Crud\UserCrudService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserCrudService $userService,
    ) {}

    public function index(Request $request)
    {
        $getAll = $request->boolean('all');
        $onlyInactive = $request->boolean('inactive');

        $users = User::query()
            ->select('users.*')
            ->tap(fn ($query) => User::applyBillingSummary($query))
            ->when(! $onlyInactive && ! $getAll, fn ($query) => $query->where('users.is_active', '=', true))
            ->when($onlyInactive, fn ($query) => $query->where('users.is_active', '=', false))
            ->orderByRaw('GREATEST(0, -IFNULL(users.balance, 0)) DESC')
            ->orderBy('created_at')
            ->get();

        return $this->inertia('Users/Index', [
            'filters' => [
                'all' => $getAll,
                'inactive' => $onlyInactive,
            ],
            'users' => UserResource::collection($users)->toArray($request),
        ]);
    }

    public function create(Request $request)
    {
        $payments = CurrentPayment::select(['id', 'start_date', 'end_date', 'amount'])
            ->orderByDesc('start_date')
            ->get();

        return $this->inertia('Users/Form', [
            'mode' => 'create',
            'submit_url' => route('users.store'),
            'user' => null,
            'payments' => CurrentPaymentResource::collection($payments)->toArray($request),
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $this->userService->create($request->toDto());

        return redirect()->route('users.index');
    }

    public function edit(Request $request, User $user)
    {
        $payments = CurrentPayment::select(['id', 'start_date', 'end_date', 'amount'])
            ->orderByDesc('start_date')
            ->get();

        $user->load([
            'transactions' => function ($query) {
                $query->with('type')->latest();
            },
            'configs',
            'vlessConfigs.server',
            'vlessConfigs.user',
        ]);

        return $this->inertia('Users/Form', [
            'mode' => 'edit',
            'submit_url' => route('users.update', $user),
            'user' => (new UserResource($user))->toArray($request),
            'payments' => CurrentPaymentResource::collection($payments)->toArray($request),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->userService->update($user, $request->toDto());

        return redirect()->route('users.index');
    }

    public function destroy(User $user)
    {
        $this->userService->delete($user);

        return redirect()->route('users.index');
    }

    public function configs(Request $request, UserToken $userToken)
    {
        $isPasswordCorrect = $userToken->validateToken($request->password);

        if ($isPasswordCorrect && empty($userToken->expires_at)) {
            $userToken->update([
                'expires_at' => now()->addMinutes(10),
            ]);
        }

        return $this->inertia('Users/Configs', [
            'token' => [
                ...(new UserTokenResource($userToken))->toArray($request),
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
