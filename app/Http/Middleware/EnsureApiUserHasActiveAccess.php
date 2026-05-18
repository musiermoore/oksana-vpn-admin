<?php

namespace App\Http\Middleware;

use App\Support\BotApiMessages;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiUserHasActiveAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('apiUser');

        if (! $user?->hasActiveAccess()) {
            return response()->json([
                'type' => 'debt',
                'message' => BotApiMessages::accessRequiresPayment(),
            ], 403);
        }

        return $next($request);
    }
}
