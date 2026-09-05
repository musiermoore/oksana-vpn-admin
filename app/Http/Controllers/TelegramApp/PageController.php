<?php

declare(strict_types=1);

namespace App\Http\Controllers\TelegramApp;

use App\Http\Controllers\Controller;
use App\Support\PublicAppUrl;

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
                'login' => $this->appRoute($routePrefix, 'login'),
                'register' => $this->appRoute($routePrefix, 'register'),
                'home' => $this->appRoute($routePrefix, 'home'),
                'wireguard' => $this->appRoute($routePrefix, 'pages.wireguard'),
                'vless' => $this->appRoute($routePrefix, 'pages.vless'),
                'vless_wl' => $this->appRoute($routePrefix, 'pages.vless-wl'),
                'payments' => $this->appRoute($routePrefix, 'pages.payments'),
                'help' => $this->appRoute($routePrefix, 'pages.help'),
                'chats' => $this->appRoute($routePrefix, 'pages.chats'),
                'support' => $this->appRoute($routePrefix, 'pages.support'),
                'giveaway' => $this->appRoute($routePrefix, 'pages.giveaway'),
                'giveaway_summary' => $this->appRoute($routePrefix, 'giveaway.summary'),
                'referrals' => $this->appRoute($routePrefix, 'pages.referrals'),
            ],
            'auth_url' => $this->appRoute($routePrefix, 'auth.telegram'),
            'password_auth_url' => $this->appRoute($routePrefix, 'auth.password'),
            'password_registration_url' => $this->appRoute($routePrefix, 'auth.register'),
            'profile_url' => $this->appRoute($routePrefix, 'me'),
            'wireguard_configs_url' => $this->appRoute($routePrefix, 'wireguard.configs.index'),
            'vless_link_url' => $this->appRoute($routePrefix, 'vless.link'),
            'vless_qr_url' => $this->appRoute($routePrefix, 'vless.qr-code'),
            'vless_send_qr_url' => $this->appRoute($routePrefix, 'vless.send-qr'),
            'vless_wl_link_url' => $this->appRoute($routePrefix, 'vless-wl.link'),
            'vless_wl_qr_url' => $this->appRoute($routePrefix, 'vless-wl.qr-code'),
            'vless_wl_send_qr_url' => $this->appRoute($routePrefix, 'vless-wl.send-qr'),
            'support_tickets_url' => $this->appRoute($routePrefix, 'support.tickets.index'),
            'support_ticket_store_url' => $this->appRoute($routePrefix, 'support.tickets.store'),
            'subscription_packages_url' => $this->appRoute($routePrefix, 'subscription-packages'),
            'claim_referral_url' => $this->appRoute($routePrefix, 'referrals.claim'),
            'giveaway_url' => $this->appRoute($routePrefix, 'giveaway.show'),
            'giveaway_participate_url' => $this->appRoute($routePrefix, 'giveaway.participate'),
            'payment_url' => $this->appRoute($routePrefix, 'payments.subscriptions'),
            'activate_subscription_code_url' => $this->appRoute($routePrefix, 'payments.subscription-codes.activate'),
            ...$extra,
        ]);
    }

    private function appRoute(string $routePrefix, string $name, array $parameters = []): string
    {
        if ($routePrefix !== 'public') {
            return route($routePrefix.'.'.$name, $parameters);
        }

        return PublicAppUrl::toVisibleUrl(
            route($routePrefix.'.'.$name, $parameters, absolute: false),
            request()
        );
    }

    private function routeNamePrefix(): string
    {
        $routeName = (string) request()->route()?->getName();

        return str_starts_with($routeName, 'public.') ? 'public' : 'telegram-app';
    }
}
