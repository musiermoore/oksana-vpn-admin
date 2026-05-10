<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyAssetUrlTest extends TestCase
{
    public function test_asset_urls_use_https_when_forwarded_proto_is_https(): void
    {
        Route::get('/_test-asset-url', fn () => response(asset('build/assets/app.css')));

        $response = $this->call('GET', '/_test-asset-url', server: [
            'HTTP_HOST' => 'domain.com',
            'HTTP_X_FORWARDED_HOST' => 'domain.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $response->assertOk();
        $response->assertSee('https://domain.com/build/assets/app.css', false);
    }
}
