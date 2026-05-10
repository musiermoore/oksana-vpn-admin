<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsInertiaData;
use App\Http\Requests\UserToken\StoreUserTokenRequest;
use App\Models\User;
use App\Models\UserToken;
use App\Services\Crud\UserTokenCrudService;
use Carbon\Carbon;

class UserTokenController extends Controller
{
    use BuildsInertiaData;

    public function __construct(
        private readonly UserTokenCrudService $userTokenService,
    ) {}

    public function index()
    {
        $userTokens = UserToken::query()
            ->where('expires_at', '>', now())
            ->orWhereNull('expires_at')
            ->get();

        return $this->inertia('UserTokens/Index', [
            'user_tokens' => $userTokens->map(fn (UserToken $userToken) => $this->userTokenData($userToken))->values(),
        ]);
    }

    public function create()
    {
        $users = User::get();

        return $this->inertia('UserTokens/Create', [
            'submit_url' => route('user-tokens.store'),
            'users' => $users->map(fn (User $user) => $this->userData($user))->values(),
        ]);
    }

    public function store(StoreUserTokenRequest $request)
    {
        $token = $this->userTokenService->create($request->toDto());

        return redirect()->route('user-tokens.show', $token->id);
    }

    public function destroy(UserToken $userToken)
    {
        $this->userTokenService->delete($userToken);

        return redirect()->route('user-tokens.index');
    }

    public function show(UserToken $userToken)
    {
        if ($userToken->expires_at && now()->gte(Carbon::parse($userToken->expires_at))) {
            abort(404);
        }

        return $this->inertia('UserTokens/Show', [
            'user_token' => [
                ...$this->userTokenData($userToken),
                'download_items' => $userToken->user->configs->map(function ($config) use ($userToken) {
                    $params = [
                        'userToken' => $userToken->token,
                        'config' => $config->id,
                        'password' => $userToken->password,
                    ];

                    return [
                        'id' => $config->id,
                        'name' => $config->name,
                        'qr_code_url' => route('users.configs.qr-code', $params),
                        'download_url' => route('users.configs.download', $params),
                    ];
                })->values(),
            ],
        ]);
    }
}
