<?php

namespace App\Http\Controllers\TelegramApp;

use App\Http\Controllers\Controller;

class PageController extends Controller
{
    public function home()
    {
        return $this->page('TelegramApp/Home');
    }

    public function payments()
    {
        return $this->page('TelegramApp/Payments');
    }

    public function support()
    {
        return $this->page('TelegramApp/Support');
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
                'home' => route('telegram-app.home'),
                'payments' => route('telegram-app.pages.payments'),
                'support' => route('telegram-app.pages.support'),
            ],
            'auth_url' => route('telegram-app.auth.telegram'),
            'profile_url' => route('telegram-app.me'),
            'support_tickets_url' => route('telegram-app.support.tickets.index'),
            'support_ticket_store_url' => route('telegram-app.support.tickets.store'),
            'subscription_packages_url' => route('telegram-app.subscription-packages'),
            'claim_referral_url' => route('telegram-app.referrals.claim'),
            'payment_url' => route('telegram-app.payments.subscriptions'),
            ...$extra,
        ]);
    }
}
