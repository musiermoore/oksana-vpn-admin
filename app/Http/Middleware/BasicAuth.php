<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BasicAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isUnauthorized($request)) {
            return response('Unauthorized', 401, [
                'Cache-Control' => 'no-cache, must-revalidate, max-age=0',
                'WWW-Authenticate' => 'Basic realm="Access denied"',
            ]);
        }

        $request->attributes->set('isAuthorized', true);

        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-cache, must-revalidate, max-age=0');

        return $response;
    }

    private function isUnauthorized(Request $request): bool
    {
        $AUTH_USER = config('auth.basic_auth.login');
        $AUTH_PASS = config('auth.basic_auth.password');

        if (empty($AUTH_USER) || empty($AUTH_PASS)) {
            return false;
        }

        $providedUser = $request->getUser() ?: ($_SERVER['PHP_AUTH_USER'] ?? null);
        $providedPassword = $request->getPassword() ?: ($_SERVER['PHP_AUTH_PW'] ?? null);

        return empty($providedUser)
            || empty($providedPassword)
            || $providedUser != $AUTH_USER
            || $providedPassword != $AUTH_PASS;
    }
}
