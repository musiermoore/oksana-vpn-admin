<?php

namespace App\Http\Middleware;

use App\Services\UserApiService;
use App\Support\BotApiMessages;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveApiUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $telegramId = (string) $request->route('telegramId');
        $user = UserApiService::instance($telegramId)->getUser();

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
