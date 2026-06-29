<?php

namespace Tests\Feature;

use App\Jobs\DispatchDefaultConfigsForUserJob;
use App\Models\SubscriptionCode;
use App\Models\TelegramAppToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TelegramAppSubscriptionCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-29 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_telegram_app_user_can_activate_gift_subscription_code(): void
    {
        Queue::fake();

        $buyer = User::factory()->create([
            'telegram' => '@buyer',
            'telegram_id' => '111',
        ]);

        $recipient = User::factory()->create([
            'telegram' => '@recipient',
            'telegram_id' => '222',
        ]);

        $plainTextToken = str_repeat('b', 80);

        TelegramAppToken::query()->create([
            'user_id' => $recipient->id,
            'token_hash' => hash('sha256', $plainTextToken),
            'last_used_at' => now(),
        ]);

        $code = SubscriptionCode::query()->create([
            'buyer_user_id' => $buyer->id,
            'code' => 'ABCD1234EFGH',
            'months' => 3,
            'days' => 90,
            'price' => 405,
            'status' => SubscriptionCode::STATUS_ACTIVE,
        ]);

        $this->withToken($plainTextToken)
            ->postJson('/telegram-app/payments/subscription-codes/activate', [
                'code' => 'ABCD 1234 EFGH',
            ])->assertOk()
            ->assertExactJson([
                'status' => 'activated',
                'message' => 'Код активирован. Подписка уже применена к вашему аккаунту.',
                'code' => 'ABCD1234EFGH',
            ]);

        $this->assertDatabaseHas('subscription_codes', [
            'id' => $code->id,
            'activated_by_user_id' => $recipient->id,
            'status' => SubscriptionCode::STATUS_ACTIVATED,
        ]);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $recipient->id,
            'start_date' => '2026-06-29',
            'end_date' => '2026-09-29',
            'price' => 405,
            'source' => 'gift_code',
        ]);

        Queue::assertPushed(DispatchDefaultConfigsForUserJob::class);
    }
}
