<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CurrentPaymentResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserTokenResource;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\XrayCustomConfig\StoreXrayCustomConfigUserPriceRequest;
use App\Http\Requests\XrayCustomConfig\UpdateXrayCustomConfigUserPriceRequest;
use App\Models\CurrentPayment;
use App\Models\User;
use App\Models\UserToken;
use App\Models\XrayCustomConfig;
use App\Models\XrayCustomConfigUser;
use App\Models\XrayCustomConfigUserPrice;
use App\Services\Crud\UserCrudService;
use App\Services\XrayCustomConfigUserPriceService;
use App\Services\VlessDeepLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserCrudService $userService,
        private readonly VlessDeepLinkService $vlessDeepLinks,
    ) {}

    public function index(Request $request)
    {
        $getAll = $request->boolean('all');
        $onlyInactive = $request->boolean('inactive');

        $users = User::query()
            ->select('users.*')
            ->whereNull('users.is_health_check_user')
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

        $xrayCustomConfigAssignments = $user->xrayCustomConfigAssignments()
            ->with(['xrayCustomConfig', 'prices'])
            ->get()
            ->map(fn ($assignment): array => [
                'id' => (int) $assignment->id,
                'config' => [
                    'id' => (int) $assignment->xrayCustomConfig->id,
                    'name' => $assignment->xrayCustomConfig->name,
                    'slug' => $assignment->xrayCustomConfig->slug,
                ],
                'prices' => $assignment->prices->map(fn ($price): array => [
                    'id' => (int) $price->id,
                    'price' => (float) $price->price,
                    'effective_from' => $price->effective_from->format('Y-m-d'),
                    'update_url' => route('users.xray-custom-config-prices.update', [$user, $assignment, $price]),
                ])->values(),
                'destroy_url' => route('users.xray-custom-config-assignments.destroy', [$user, $assignment]),
            ])->values();

        return $this->inertia('Users/Form', [
            'mode' => 'edit',
            'submit_url' => route('users.update', $user),
            'user' => (new UserResource($user))->toArray($request),
            'payments' => CurrentPaymentResource::collection($payments)->toArray($request),
            'xray_custom_config_assignments' => $xrayCustomConfigAssignments,
            'available_xray_custom_configs' => XrayCustomConfig::query()
                ->ordered()
                ->get(['id', 'name', 'slug'])
                ->map(fn (XrayCustomConfig $config): array => [
                    'id' => (int) $config->id,
                    'name' => $config->name,
                    'slug' => $config->slug,
                ])
                ->values(),
            'xray_custom_config_price_store_url' => route('users.xray-custom-config-prices.store', $user),
            'subscription_links' => [
                'standard' => $this->vlessDeepLinks->getConnectUrl($user),
                'connect_v2' => $this->vlessDeepLinks->getConnectV2Url($user),
                'whitelist' => $this->vlessDeepLinks->getConnectUrlForRoute($user, 'vless.connect-wl'),
            ],
        ]);
    }

    public function storeXrayCustomConfigPrice(
        StoreXrayCustomConfigUserPriceRequest $request,
        User $user,
        XrayCustomConfigUserPriceService $service,
    ): RedirectResponse {
        $service->store($user, $request->toData());

        return redirect()->route('users.edit', $user)
            ->with('success', 'Personal Xray config price saved.');
    }

    public function updateXrayCustomConfigPrice(
        UpdateXrayCustomConfigUserPriceRequest $request,
        User $user,
        XrayCustomConfigUser $assignment,
        XrayCustomConfigUserPrice $price,
        XrayCustomConfigUserPriceService $service,
    ): RedirectResponse {
        $assignment = $user->xrayCustomConfigAssignments()
            ->whereKey($assignment->getKey())
            ->firstOrFail();
        $price = $assignment->prices()
            ->whereKey($price->getKey())
            ->firstOrFail();

        $service->update($price, $request->toData());

        return redirect()->route('users.edit', $user)
            ->with('success', 'Personal Xray config price updated.');
    }

    public function destroyXrayCustomConfigAssignment(User $user, XrayCustomConfigUser $assignment): RedirectResponse
    {
        $user->xrayCustomConfigAssignments()
            ->whereKey($assignment->getKey())
            ->firstOrFail()
            ->delete();

        return redirect()->route('users.edit', $user)
            ->with('success', 'Personal Xray config assignment removed.');
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
