<?php

namespace App\Http\Middleware;

use App\Services\TelegramApp\TelegramMiniAppAuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveTelegramAppUser
{
    public function __construct(
        private readonly TelegramMiniAppAuthService $authService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $user = $this->authService->resolveUserByToken($token);

        if (! $user) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 401);
        }

        $request->attributes->set('telegramAppUser', $user);
        $request->setUserResolver(fn () => $user);
        Auth::guard()->setUser($user);

        return $next($request);
    }
}
