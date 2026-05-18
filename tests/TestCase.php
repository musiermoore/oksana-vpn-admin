<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';

        $cachedConfigPath = __DIR__.'/../bootstrap/cache/config.php';
        $cachedRoutesPath = __DIR__.'/../bootstrap/cache/routes-v7.php';

        if (is_file($cachedConfigPath)) {
            @rename($cachedConfigPath, $cachedConfigPath.'.bak.testing');
        }

        if (is_file($cachedRoutesPath)) {
            @rename($cachedRoutesPath, $cachedRoutesPath.'.bak.testing');
        }

        try {
            /** @var \Illuminate\Foundation\Application $app */
            $app = require __DIR__.'/../bootstrap/app.php';
            $app->make(Kernel::class)->bootstrap();

            return $app;
        } finally {
            if (is_file($cachedConfigPath.'.bak.testing') && ! is_file($cachedConfigPath)) {
                @rename($cachedConfigPath.'.bak.testing', $cachedConfigPath);
            }

            if (is_file($cachedRoutesPath.'.bak.testing') && ! is_file($cachedRoutesPath)) {
                @rename($cachedRoutesPath.'.bak.testing', $cachedRoutesPath);
            }
        }
    }
}
