<?php

declare(strict_types=1);

namespace App\Http\Controllers\TelegramApp;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class PageController extends Controller
{
    public function login(): RedirectResponse
    {
        return $this->redirectTo('login');
    }

    public function register(): RedirectResponse
    {
        return $this->redirectTo('register');
    }

    public function home(): RedirectResponse
    {
        return $this->redirectTo('');
    }

    public function payments(): RedirectResponse
    {
        return $this->redirectTo('payments');
    }

    public function wireGuard(): RedirectResponse
    {
        return $this->redirectTo('wireguard');
    }

    public function vless(): RedirectResponse
    {
        return $this->redirectTo('vless');
    }

    public function vlessWhiteList(): RedirectResponse
    {
        return $this->redirectTo('vless-wl');
    }

    public function help(): RedirectResponse
    {
        return $this->redirectTo('help');
    }

    public function chats(): RedirectResponse
    {
        return $this->redirectTo('chats');
    }

    public function support(): RedirectResponse
    {
        return $this->redirectTo('support');
    }

    public function giveaway(): RedirectResponse
    {
        return $this->redirectTo('giveaway');
    }

    public function referrals(): RedirectResponse
    {
        return $this->redirectTo('referrals');
    }

    public function supportShow(int $ticketId): RedirectResponse
    {
        return $this->redirectTo("support/{$ticketId}");
    }

    private function redirectTo(string $path): RedirectResponse
    {
        $url = rtrim((string) config('services.telegram.mini_app_url'), '/').'/'.ltrim($path, '/');
        $query = request()->getQueryString();

        return redirect()->away($query ? "{$url}?{$query}" : $url);
    }
}
