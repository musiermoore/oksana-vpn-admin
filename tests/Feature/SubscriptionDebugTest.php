<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\SubscriptionDebug\StoreSubscriptionDebugData;
use App\Models\User;
use App\Services\SubscriptionDebugService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class SubscriptionDebugTest extends TestCase
{
    public function test_authenticated_user_can_open_the_editor_and_create_a_json_subscription(): void
    {
        Storage::fake('local');
        Redis::shouldReceive('get')->twice()->with('subscription-debug:uuid')->andReturn('');
        Redis::shouldReceive('ttl')->once()->with('subscription-debug:uuid')->andReturn(-2);
        Redis::shouldReceive('setex')->once()->withArgs(function (string $key, int $ttl, string $value): bool {
            return $key === 'subscription-debug:uuid'
                && $ttl === 3600
                && (bool) preg_match('/^[0-9a-f-]{36}\|(json|url)$/', $value);
        });

        $admin = User::query()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
        ]);

        $this->actingAs($admin)
            ->get('/subscription-debug')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('SubscriptionDebug/Index'));

        $response = $this->actingAs($admin)->post('/subscription-debug', [
            'type' => 'json',
            'body' => '{"dns":{},"outbounds":{}}',
        ]);

        $response->assertRedirect('/subscription-debug');
        $created = session('subscription_debug_created');

        self::assertIsArray($created);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $created['uuid']);
        Storage::disk('local')->assertExists('subscription-debug/body');
    }

    public function test_public_json_subscription_is_available_until_redis_marker_expires(): void
    {
        Storage::fake('local');
        $uuid = (string) Str::uuid();
        Storage::disk('local')->put('subscription-debug/body', '{"dns":{}}');
        Redis::shouldReceive('get')->once()->with('subscription-debug:uuid')->andReturn($uuid.'|json');

        $response = $this->get('/subscription-debug?uuid='.$uuid);

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="subscription.json"')
            ->assertSee('{"dns":{}}', false);
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_saving_again_reuses_the_active_uuid_and_remaining_ttl(): void
    {
        Storage::fake('local');
        $uuid = (string) Str::uuid();
        Redis::shouldReceive('get')->once()->with('subscription-debug:uuid')->andReturn($uuid.'|json');
        Redis::shouldReceive('ttl')->once()->with('subscription-debug:uuid')->andReturn(1700);
        Redis::shouldReceive('setex')->once()->with('subscription-debug:uuid', 1700, $uuid.'|url');

        $storedUuid = app(SubscriptionDebugService::class)->store(new StoreSubscriptionDebugData(
            type: 'url',
            body: 'vless://updated',
        ));

        self::assertSame($uuid, $storedUuid);
        Storage::disk('local')->assertExists('subscription-debug/body');
    }

    public function test_public_subscription_is_not_available_without_a_redis_marker(): void
    {
        Storage::fake('local');
        $uuid = (string) Str::uuid();
        Storage::disk('local')->put('subscription-debug/body', "vless://example\n");
        Redis::shouldReceive('get')->once()->with('subscription-debug:uuid')->andReturn('another-uuid|url');

        $this->get('/subscription-debug?uuid='.$uuid)->assertNotFound();
    }

    public function test_public_url_subscription_is_returned_as_plain_text(): void
    {
        Storage::fake('local');
        $uuid = (string) Str::uuid();
        Storage::disk('local')->put('subscription-debug/body', "vless://example\nvmess://example\n");
        Redis::shouldReceive('get')->once()->with('subscription-debug:uuid')->andReturn($uuid.'|url');

        $this->get('/subscription-debug?uuid='.$uuid)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertHeaderMissing('Content-Disposition')
            ->assertSee('vless://example', false)
            ->assertSee('vmess://example', false);
    }

    public function test_editor_requires_authentication_when_uuid_is_not_present(): void
    {
        $this->get('/subscription-debug')->assertRedirect('/login');
    }
}
