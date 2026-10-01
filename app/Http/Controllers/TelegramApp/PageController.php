<?php

declare(strict_types=1);

namespace App\Http\Controllers\TelegramApp;

use App\Http\Controllers\Controller;
use Inertia\Response;

class PageController extends Controller
{
    public function login(): Response
    {
        return $this->inertia('TelegramApp/Login');
    }

    public function register(): Response
    {
        return $this->inertia('TelegramApp/Register');
    }

    public function home(): Response
    {
        return $this->inertia('TelegramApp/Home');
    }

    public function payments(): Response
    {
        return $this->inertia('TelegramApp/Payments');
    }

    public function wireGuard(): Response
    {
        return $this->inertia('TelegramApp/WireGuard');
    }

    public function vless(): Response
    {
        return $this->inertia('TelegramApp/Vless');
    }

    public function vlessWhiteList(): Response
    {
        return $this->inertia('TelegramApp/VlessWhiteList');
    }

    public function help(): Response
    {
        return $this->inertia('TelegramApp/Help');
    }

    public function chats(): Response
    {
        return $this->inertia('TelegramApp/Chats');
    }

    public function support(): Response
    {
        return $this->inertia('TelegramApp/Support');
    }

    public function giveaway(): Response
    {
        return $this->inertia('TelegramApp/Giveaway');
    }

    public function referrals(): Response
    {
        return $this->inertia('TelegramApp/Referrals');
    }

    public function supportShow(int $ticketId): Response
    {
        return $this->inertia('TelegramApp/SupportShow');
    }
}
