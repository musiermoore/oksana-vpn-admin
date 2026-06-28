<?php

namespace Tests\Feature;

use App\Enums\ReferralRewardStatus;
use App\Models\TelegramAppToken;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramAppReferralClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-28 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_authenticated_user_can_claim_referrer_once_and_backfill_old_reward(): void
    {
        [$user, $token] = $this->createAuthorizedUser();

        $referrer = User::query()->create([
            'name' => 'Referrer',
            'telegram' => '@referrer',
            'telegram_id' => '555',
        ]);

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_SUBSCRIPTION),
            'amount' => -405,
            'is_approved' => true,
            'description' => 'Покупка подписки на 3 мес.',
        ]);

        $transaction->forceFill([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ])->save();

        $response = $this->withToken($token)->postJson('/telegram-app/referrals/claim', [
            'referral' => 'https://t.me/test_bot?start=ref_'.$referrer->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('user.referral.can_claim', false)
            ->assertJsonPath('user.referral.accumulated_discount_percent', 0);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'referrer_id' => $referrer->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $referrer->id,
            'referral_accumulated_discount_percent' => 10,
        ]);

        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $referrer->id,
            'referral_user_id' => $user->id,
            'reward_status' => ReferralRewardStatus::Rewarded->value,
        ]);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'source' => 'referral_bonus',
            'price' => 0,
        ]);

        $this->withToken($token)->postJson('/telegram-app/referrals/claim', [
            'referral' => 'ref_999',
        ])->assertStatus(422);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function createAuthorizedUser(): array
    {
        $user = User::factory()->create([
            'telegram' => '@alice',
            'telegram_id' => '123456789',
        ]);

        $plainTextToken = str_repeat('c', 80);

        TelegramAppToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainTextToken),
            'last_used_at' => now(),
        ]);

        return [$user, $plainTextToken];
    }
}
