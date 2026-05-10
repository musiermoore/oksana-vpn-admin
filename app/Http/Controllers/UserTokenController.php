<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Http\Resources\UserTokenResource;
use App\Http\Requests\UserToken\StoreUserTokenRequest;
use App\Models\User;
use App\Models\UserToken;
use App\Services\Crud\UserTokenCrudService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class UserTokenController extends Controller
{
    public function __construct(
        private readonly UserTokenCrudService $userTokenService,
    ) {}

    public function index(Request $request)
    {
        $userTokens = UserToken::query()
            ->where('expires_at', '>', now())
            ->orWhereNull('expires_at')
            ->get();

        return $this->inertia('UserTokens/Index', [
            'user_tokens' => UserTokenResource::collection($userTokens)->toArray($request),
        ]);
    }

    public function create(Request $request)
    {
        $users = User::get();

        return $this->inertia('UserTokens/Create', [
            'submit_url' => route('user-tokens.store'),
            'users' => UserResource::collection($users)->toArray($request),
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
                ...(new UserTokenResource($userToken))->resolve(),
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
