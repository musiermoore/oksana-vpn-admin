<?php

namespace App\Providers;

use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Vite;
use Telegram\Bot\BotsManager;
use Telegram\Bot\HttpClients\GuzzleHttpClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->extend(BotsManager::class, function (BotsManager $manager, $app): BotsManager {
            $proxy = (string) config('telegram.proxy', '');

            if ($proxy === '') {
                return $manager;
            }

            $config = config('telegram');
            $config['http_client_handler'] = new GuzzleHttpClient(new Client([
                'proxy' => $proxy,
            ]));

            return (new BotsManager($config))->setContainer($app);
        });
    }

    public function boot(): void
    {
        if (! $this->app->isLocal()) {
            Vite::useHotFile(storage_path('framework/vite.hot'));
        }
    }
}
