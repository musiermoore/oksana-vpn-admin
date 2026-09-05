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
        return $this->inertia($component, [
            'routes' => [
                'login' => route('telegram-app.login'),
                'register' => route('telegram-app.register'),
                'home' => route('telegram-app.home'),
                'wireguard' => route('telegram-app.pages.wireguard'),
                'vless' => route('telegram-app.pages.vless'),
                'vless_wl' => route('telegram-app.pages.vless-wl'),
                'payments' => route('telegram-app.pages.payments'),
                'help' => route('telegram-app.pages.help'),
                'chats' => route('telegram-app.pages.chats'),
                'support' => route('telegram-app.pages.support'),
                'giveaway' => route('telegram-app.pages.giveaway'),
                'giveaway_summary' => route('telegram-app.giveaway.summary'),
                'referrals' => route('telegram-app.pages.referrals'),
            ],
            'auth_url' => route('telegram-app.auth.telegram'),
            'password_auth_url' => route('telegram-app.auth.password'),
            'password_registration_url' => route('telegram-app.auth.register'),
            'profile_url' => route('telegram-app.me'),
            'wireguard_configs_url' => route('telegram-app.wireguard.configs.index'),
            'vless_link_url' => route('telegram-app.vless.link'),
            'vless_qr_url' => route('telegram-app.vless.qr-code'),
            'vless_send_qr_url' => route('telegram-app.vless.send-qr'),
            'vless_wl_link_url' => route('telegram-app.vless-wl.link'),
            'vless_wl_qr_url' => route('telegram-app.vless-wl.qr-code'),
            'vless_wl_send_qr_url' => route('telegram-app.vless-wl.send-qr'),
            'support_tickets_url' => route('telegram-app.support.tickets.index'),
            'support_ticket_store_url' => route('telegram-app.support.tickets.store'),
            'subscription_packages_url' => route('telegram-app.subscription-packages'),
            'claim_referral_url' => route('telegram-app.referrals.claim'),
            'giveaway_url' => route('telegram-app.giveaway.show'),
            'giveaway_participate_url' => route('telegram-app.giveaway.participate'),
            'payment_url' => route('telegram-app.payments.subscriptions'),
            'activate_subscription_code_url' => route('telegram-app.payments.subscription-codes.activate'),
            ...$extra,
        ]);
    }
}
