<?php

namespace App\Http\Controllers\TelegramApp;

use App\Http\Controllers\Controller;

class PageController extends Controller
{
    public function __invoke()
    {
        return $this->inertia('TelegramApp/Home', [
            'auth_url' => route('telegram-app.auth.telegram'),
            'profile_url' => route('telegram-app.me'),
            'support_tickets_url' => route('telegram-app.support.tickets.index'),
            'subscription_packages_url' => route('telegram-app.subscription-packages'),
            'payment_url' => route('telegram-app.payments.subscriptions'),
        ]);
    }
}
