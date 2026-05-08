<?php

namespace App\Providers;

use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Telegram\Bot\HttpClients\GuzzleHttpClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $proxy = (string) config('telegram.proxy', '');

        if ($proxy === '') {
            return;
        }

        config()->set(
            'telegram.http_client_handler',
            new GuzzleHttpClient(new Client([
                'proxy' => $proxy,
            ])),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
