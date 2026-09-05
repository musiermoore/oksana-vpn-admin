<?php

declare(strict_types=1);

namespace App\Http\Controllers\TelegramApp;

use App\Http\Controllers\Controller;

class PageController extends Controller
{
    public function login()
    {
        return $this->page('TelegramApp/Login');
    }

    public function register()
    {
        return $this->page('TelegramApp/Register');
    }

    public function home()
    {
        return $this->page('TelegramApp/Home');
    }

    public function payments()
    {
        return $this->page('TelegramApp/Payments');
    }

    public function wireGuard()
    {
        return $this->page('TelegramApp/WireGuard');
    }

    public function vless()
    {
        return $this->page('TelegramApp/Vless');
    }

    public function vlessWhiteList()
    {
        return $this->page('TelegramApp/VlessWhiteList');
    }

    public function help()
    {
        return $this->page('TelegramApp/Help');
    }

    public function chats()
    {
        return $this->page('TelegramApp/Chats');
    }

    public function support()
    {
        return $this->page('TelegramApp/Support');
    }

    public function giveaway()
    {
        return $this->page('TelegramApp/Giveaway');
    }

    public function referrals()
    {
        return $this->page('TelegramApp/Referrals');
    }

    public function supportShow(int $ticketId)
    {
        return $this->page('TelegramApp/SupportShow', [
            'ticket_id' => $ticketId,
        ]);
    }

    private function page(string $component, array $extra = [])
    {
        $routePrefix = $this->routeNamePrefix();

        return $this->inertia($component, [
            'routes' => [
                'login' => route($routePrefix.'.login'),
                'register' => route($routePrefix.'.register'),
                'home' => route($routePrefix.'.home'),
                'wireguard' => route($routePrefix.'.pages.wireguard'),
                'vless' => route($routePrefix.'.pages.vless'),
                'vless_wl' => route($routePrefix.'.pages.vless-wl'),
                'payments' => route($routePrefix.'.pages.payments'),
                'help' => route($routePrefix.'.pages.help'),
                'chats' => route($routePrefix.'.pages.chats'),
                'support' => route($routePrefix.'.pages.support'),
                'giveaway' => route($routePrefix.'.pages.giveaway'),
                'giveaway_summary' => route($routePrefix.'.giveaway.summary'),
                'referrals' => route($routePrefix.'.pages.referrals'),
            ],
            'auth_url' => route($routePrefix.'.auth.telegram'),
            'password_auth_url' => route($routePrefix.'.auth.password'),
            'password_registration_url' => route($routePrefix.'.auth.register'),
            'profile_url' => route($routePrefix.'.me'),
            'wireguard_configs_url' => route($routePrefix.'.wireguard.configs.index'),
            'vless_link_url' => route($routePrefix.'.vless.link'),
            'vless_qr_url' => route($routePrefix.'.vless.qr-code'),
            'vless_send_qr_url' => route($routePrefix.'.vless.send-qr'),
            'vless_wl_link_url' => route($routePrefix.'.vless-wl.link'),
            'vless_wl_qr_url' => route($routePrefix.'.vless-wl.qr-code'),
            'vless_wl_send_qr_url' => route($routePrefix.'.vless-wl.send-qr'),
            'support_tickets_url' => route($routePrefix.'.support.tickets.index'),
            'support_ticket_store_url' => route($routePrefix.'.support.tickets.store'),
            'subscription_packages_url' => route($routePrefix.'.subscription-packages'),
            'claim_referral_url' => route($routePrefix.'.referrals.claim'),
            'giveaway_url' => route($routePrefix.'.giveaway.show'),
            'giveaway_participate_url' => route($routePrefix.'.giveaway.participate'),
            'payment_url' => route($routePrefix.'.payments.subscriptions'),
            'activate_subscription_code_url' => route($routePrefix.'.payments.subscription-codes.activate'),
            ...$extra,
        ]);
    }

    private function routeNamePrefix(): string
    {
        $routeName = (string) request()->route()?->getName();

        return str_starts_with($routeName, 'public.') ? 'public' : 'telegram-app';
    }
}
