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
        header('Cache-Control: no-cache, must-revalidate, max-age=0');

        if ($this->isUnauthorized()) {
            header('HTTP/1.1 401 Authorization Required');
            header('WWW-Authenticate: Basic realm="Access denied"');

            exit;
        }

        $request->attributes->set('isAuthorized', true);

        return $next($request);
    }

    private function isUnauthorized(): bool
    {
        $AUTH_USER = config('auth.basic_auth.login');
        $AUTH_PASS = config('auth.basic_auth.password');

        if (empty($AUTH_USER) || empty($AUTH_PASS)) {
            return false;
        }

        return empty($_SERVER['PHP_AUTH_USER'])
            || empty($_SERVER['PHP_AUTH_PW'])
            || $_SERVER['PHP_AUTH_USER'] != $AUTH_USER
            || $_SERVER['PHP_AUTH_PW']   != $AUTH_PASS;
    }
}
