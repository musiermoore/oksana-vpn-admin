<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\SyncGiveawaysCommand;
use App\Models\Giveaway;
use App\Models\GiveawayParticipant;
use App\Models\GiveawayPrize;
use App\Models\GiveawaySeries;
use App\Models\Referral;
use App\Models\TelegramAppToken;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiveawayFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-11 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_authenticated_user_can_participate_once_and_only_eligible_referrals_are_counted(): void
    {
        [$user, $token] = $this->createAuthorizedUser([
            'name' => 'Referrer',
            'telegram' => '@referrer',
        ]);

        $giveaway = $this->createGiveaway(
            startsAt: '2026-08-10 00:00:00',
            endsAt: '2026-08-12 00:00:00',
            status: Giveaway::STATUS_ACTIVE,
        );

        $eligibleReferral = User::factory()->create();
        $expiredReferral = User::factory()->create();
        $oldReferral = User::factory()->create();

        UserSubscription::query()->create([
            'user_id' => $eligibleReferral->id,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-20',
            'price' => 0,
            'source' => 'gift_code',
        ]);

        UserSubscription::query()->create([
            'user_id' => $expiredReferral->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-11',
            'price' => 0,
            'source' => 'gift_code',
        ]);

        Referral::query()->create([
            'referrer_id' => $user->id,
            'referral_user_id' => $eligibleReferral->id,
            'created_at' => '2026-08-10 05:00:00',
            'updated_at' => '2026-08-10 05:00:00',
        ]);

        Referral::query()->create([
            'referrer_id' => $user->id,
            'referral_user_id' => $expiredReferral->id,
            'created_at' => '2026-08-10 06:00:00',
            'updated_at' => '2026-08-10 06:00:00',
        ]);

        Referral::query()->create([
            'referrer_id' => $user->id,
            'referral_user_id' => $oldReferral->id,
            'created_at' => '2026-08-09 23:00:00',
            'updated_at' => '2026-08-09 23:00:00',
        ]);

        $firstResponse = $this->withToken($token)
            ->postJson('/telegram-app/giveaway/participate');

        $firstResponse
            ->assertOk()
            ->assertJsonPath('participant.is_participant', true)
            ->assertJsonPath('participant.base_votes', 1)
            ->assertJsonPath('participant.eligible_referrals', 1)
            ->assertJsonPath('participant.total_weight', 2);

        $secondResponse = $this->withToken($token)
            ->postJson('/telegram-app/giveaway/participate');

        $secondResponse
            ->assertOk()
            ->assertJsonPath('participant.total_weight', 2);

        $this->assertDatabaseCount('giveaway_participants', 1);
        $this->assertDatabaseHas('giveaway_participants', [
            'giveaway_id' => $giveaway->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_authenticated_user_can_load_giveaway_summary_for_pending_participation_counter(): void
    {
        [$user, $token] = $this->createAuthorizedUser([
            'name' => 'Referrer',
            'telegram' => '@referrer',
        ]);

        $activeGiveaway = $this->createGiveaway(
            startsAt: '2026-08-10 00:00:00',
            endsAt: '2026-08-12 00:00:00',
            status: Giveaway::STATUS_ACTIVE,
        );

        $this->createGiveaway(
            startsAt: '2026-08-01 00:00:00',
            endsAt: '2026-08-02 00:00:00',
            status: Giveaway::STATUS_FINISHED,
        );

        $this->withToken($token)
            ->getJson('/telegram-app/giveaway/summary')
            ->assertOk()
            ->assertJsonPath('summary.active_giveaways_count', 1)
            ->assertJsonPath('summary.pending_participation_count', 1);

        GiveawayParticipant::query()->create([
            'giveaway_id' => $activeGiveaway->id,
            'user_id' => $user->id,
            'joined_at' => '2026-08-11 12:10:00',
        ]);

        $this->withToken($token)
            ->getJson('/telegram-app/giveaway/summary')
            ->assertOk()
            ->assertJsonPath('summary.active_giveaways_count', 1)
            ->assertJsonPath('summary.pending_participation_count', 0);
    }

    public function test_admins_only_giveaway_is_hidden_from_regular_users_and_visible_to_admins(): void
    {
        [$user, $token] = $this->createAuthorizedUser([
            'name' => 'Regular',
            'telegram' => '@regular',
            'is_admin' => false,
        ]);

        [$admin, $adminToken] = $this->createAuthorizedUser([
            'name' => 'Admin',
            'telegram' => '@admin',
            'is_admin' => true,
        ]);

        $this->createGiveaway(
            startsAt: '2026-08-10 00:00:00',
            endsAt: '2026-08-12 00:00:00',
            status: Giveaway::STATUS_ACTIVE,
            adminsOnly: true,
        );

        $this->withToken($token)
            ->getJson('/telegram-app/giveaway/current')
            ->assertOk()
            ->assertJsonPath('giveaway', null)
            ->assertJsonPath('participant', null);

        $this->withToken($token)
            ->getJson('/telegram-app/giveaway/summary')
            ->assertOk()
            ->assertJsonPath('summary.active_giveaways_count', 0)
            ->assertJsonPath('summary.pending_participation_count', 0);

        $this->withToken($adminToken)
            ->getJson('/telegram-app/giveaway/current')
            ->assertOk()
            ->assertJsonPath('giveaway.admins_only', true);

        $this->withToken($adminToken)
            ->getJson('/telegram-app/giveaway/summary')
            ->assertOk()
            ->assertJsonPath('summary.active_giveaways_count', 1)
            ->assertJsonPath('summary.pending_participation_count', 1);
    }

    public function test_draw_persists_winner_snapshot_and_grants_subscription_without_duplicate_wins(): void
    {
        $firstUser = User::factory()->create([
            'telegram' => '@first',
        ]);
        $secondUser = User::factory()->create([
            'telegram' => '@second',
        ]);

        $giveaway = $this->createGiveaway(
            startsAt: '2026-08-01 00:00:00',
            endsAt: '2026-08-10 20:00:00',
            status: Giveaway::STATUS_ACTIVE,
            prizeRows: [
                ['duration_months' => 1, 'quantity' => 1, 'title' => 'Подписка на 1 месяц'],
                ['duration_months' => 3, 'quantity' => 1, 'title' => 'Подписка на 3 месяца'],
            ],
        );

        GiveawayParticipant::query()->create([
            'giveaway_id' => $giveaway->id,
            'user_id' => $firstUser->id,
            'joined_at' => '2026-08-02 10:00:00',
        ]);

        GiveawayParticipant::query()->create([
            'giveaway_id' => $giveaway->id,
            'user_id' => $secondUser->id,
            'joined_at' => '2026-08-02 11:00:00',
        ]);

        $this->artisan(SyncGiveawaysCommand::class)
            ->assertSuccessful();

        $giveaway->refresh();

        $this->assertSame(Giveaway::STATUS_FINISHED, $giveaway->status);
        $this->assertDatabaseCount('giveaway_winners', 2);
        $this->assertSame(
            2,
            $giveaway->winners()->pluck('user_id')->unique()->count(),
        );
        $this->assertDatabaseHas('giveaway_winners', [
            'giveaway_id' => $giveaway->id,
            'prize_status' => 'granted',
        ]);
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $firstUser->id,
            'source' => 'giveaway',
        ]);
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $secondUser->id,
            'source' => 'giveaway',
        ]);
    }

    public function test_sync_command_creates_only_one_repeat_instance_for_finished_giveaway_series(): void
    {
        $series = GiveawaySeries::query()->create([
            'name' => 'Розыгрыш Oksana VPN',
            'auto_repeat_enabled' => true,
            'repeat_delay_minutes' => 60,
            'repeat_limit' => 2,
        ]);

        $giveaway = Giveaway::query()->create([
            'series_id' => $series->id,
            'sequence_number' => 1,
            'title' => 'Розыгрыш Oksana VPN',
            'description' => 'Тест',
            'status' => Giveaway::STATUS_ACTIVE,
            'starts_at' => '2026-08-01 00:00:00',
            'ends_at' => '2026-08-10 20:00:00',
            'duration_minutes' => 7 * 24 * 60,
        ]);

        GiveawayPrize::query()->create([
            'giveaway_id' => $giveaway->id,
            'duration_months' => 1,
            'quantity' => 3,
            'title' => 'Подписка на 1 месяц',
            'sort_order' => 0,
        ]);

        $this->artisan(SyncGiveawaysCommand::class)->assertSuccessful();
        $this->artisan(SyncGiveawaysCommand::class)->assertSuccessful();

        $this->assertDatabaseCount('giveaways', 2);
        $nextGiveaway = Giveaway::query()
            ->where('parent_giveaway_id', $giveaway->id)
            ->first();

        $this->assertNotNull($nextGiveaway);
        $this->assertNotSame($giveaway->id, $nextGiveaway->id);
        $this->assertSame(2, $nextGiveaway->sequence_number);
        $this->assertSame(0, $nextGiveaway->participants()->count());
        $this->assertSame(0, $nextGiveaway->winners()->count());
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array{0: User, 1: string}
     */
    private function createAuthorizedUser(array $attributes = []): array
    {
        $user = User::factory()->create(array_merge([
            'join_at' => now()->toDateString(),
        ], $attributes));

        $plainTextToken = str_pad("giveaway-test-token-{$user->id}", 80, 'g');

        TelegramAppToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainTextToken),
            'last_used_at' => now(),
        ]);

        return [$user, $plainTextToken];
    }

    /**
     * @param array<int, array{duration_months:int,quantity:int,title:string}> $prizeRows
     */
    private function createGiveaway(
        string $startsAt,
        string $endsAt,
        string $status,
        array $prizeRows = [['duration_months' => 1, 'quantity' => 1, 'title' => 'Подписка на 1 месяц']],
        bool $adminsOnly = false,
    ): Giveaway {
        $series = GiveawaySeries::query()->create([
            'name' => 'Розыгрыш Oksana VPN',
            'auto_repeat_enabled' => false,
            'repeat_delay_minutes' => 0,
            'repeat_limit' => null,
        ]);

        $giveaway = Giveaway::query()->create([
            'series_id' => $series->id,
            'sequence_number' => 1,
            'title' => 'Розыгрыш Oksana VPN',
            'description' => 'Тестовый розыгрыш',
            'admins_only' => $adminsOnly,
            'status' => $status,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'duration_minutes' => Carbon::parse($startsAt)->diffInMinutes(Carbon::parse($endsAt)),
        ]);

        foreach ($prizeRows as $index => $row) {
            GiveawayPrize::query()->create([
                'giveaway_id' => $giveaway->id,
                'duration_months' => $row['duration_months'],
                'quantity' => $row['quantity'],
                'title' => $row['title'],
                'sort_order' => $index,
            ]);
        }

        return $giveaway;
    }
}
