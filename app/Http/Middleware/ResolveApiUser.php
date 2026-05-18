<?php

namespace App\Http\Middleware;

use App\Services\Api\ApiUserService;
use App\Support\BotApiMessages;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveApiUser
{
    public function __construct(
        private readonly ApiUserService $userService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $telegramId = (string) $request->route('telegramId');
        $user = $this->userService->findUserByTelegramId($telegramId);

        if (! $user) {
            return response()->json([
                'message' => BotApiMessages::userNotFound(),
            ], 404);
        }

        $request->attributes->set('apiUser', $user);
        $request->setUserResolver(fn () => $user);
        Auth::guard()->setUser($user);

        return $next($request);
    }
}
