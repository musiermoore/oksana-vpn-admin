<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\CurrentPayment;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiUserRegistrationStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_status_returns_true_for_active_existing_user(): void
    {
        Message::query()->create([
            'name' => 'Welcome Basic',
            'slug' => Message::SLUG_WELCOME_BASIC,
            'text' => 'Базовое сообщение',
        ]);

        Message::query()->create([
            'name' => 'Welcome Extended',
            'slug' => Message::SLUG_WELCOME_EXTENDED,
            'text' => 'Расширенное сообщение',
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
            'balance' => 500,
            'is_active' => true,
        ]);

        $period = CurrentPayment::query()->create([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'amount' => 300,
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => $period->start_date,
            'end_date' => $period->end_date,
            'price' => 300,
        ]);

        $this->getJson('/api/users/123456789/registration-status')
            ->assertOk()
            ->assertExactJson([
                'registered' => true,
                'active_subscription_end_date' => $period->end_date,
                'has_money_for_next_subscription_month' => true,
                'welcome_text' => 'Расширенное сообщение',
            ]);

        $this->assertNotNull($user->fresh()->welcome_text_seen_at);
    }

    public function test_registration_status_returns_false_for_missing_user(): void
    {
        Message::query()->create([
            'name' => 'Welcome Basic',
            'slug' => Message::SLUG_WELCOME_BASIC,
            'text' => 'Базовое сообщение',
        ]);

        $this->getJson('/api/users/999999/registration-status')
            ->assertOk()
            ->assertExactJson([
                'registered' => false,
                'active_subscription_end_date' => null,
                'has_money_for_next_subscription_month' => false,
                'welcome_text' => 'Базовое сообщение',
            ]);
    }

    public function test_registration_status_returns_false_for_inactive_or_deleted_user(): void
    {
        User::query()->create([
            'name' => 'Inactive',
            'telegram' => '@inactive',
            'telegram_id' => '111111',
            'balance' => 500,
            'is_active' => false,
        ]);

        $deletedUser = User::query()->create([
            'name' => 'Deleted',
            'telegram' => '@deleted',
            'telegram_id' => '222222',
            'balance' => 500,
            'is_active' => true,
        ]);
        $deletedUser->delete();

        $this->getJson('/api/users/111111/registration-status')
            ->assertOk()
            ->assertExactJson([
                'registered' => false,
                'active_subscription_end_date' => null,
                'has_money_for_next_subscription_month' => false,
                'welcome_text' => '',
            ]);

        $this->getJson('/api/users/222222/registration-status')
            ->assertOk()
            ->assertExactJson([
                'registered' => false,
                'active_subscription_end_date' => null,
                'has_money_for_next_subscription_month' => false,
                'welcome_text' => '',
            ]);
    }

    public function test_registration_status_returns_false_when_balance_is_not_enough_for_next_month(): void
    {
        $user = User::query()->create([
            'name' => 'Bob',
            'telegram' => '@bob',
            'telegram_id' => '555555',
            'balance' => 199,
            'is_active' => true,
        ]);

        $period = CurrentPayment::query()->create([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
            'amount' => 200,
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => $period->start_date,
            'end_date' => $period->end_date,
            'price' => 200,
        ]);

        $this->getJson('/api/users/555555/registration-status')
            ->assertOk()
            ->assertExactJson([
                'registered' => true,
                'active_subscription_end_date' => $period->end_date,
                'has_money_for_next_subscription_month' => false,
                'welcome_text' => '',
            ]);
    }

    public function test_registration_status_returns_max_end_date_when_user_has_active_and_future_subscriptions(): void
    {
        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '777777',
            'balance' => 500,
            'is_active' => true,
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'price' => 150,
        ]);

        $futureEndDate = now()->addMonths(2)->toDateString();

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => $futureEndDate,
            'price' => 300,
        ]);

        CurrentPayment::query()->create([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'amount' => 300,
        ]);

        $this->getJson('/api/users/777777/registration-status')
            ->assertOk()
            ->assertExactJson([
                'registered' => true,
                'active_subscription_end_date' => $futureEndDate,
                'has_money_for_next_subscription_month' => true,
                'welcome_text' => '',
            ]);
    }

    public function test_registration_status_returns_basic_message_when_extended_was_seen_less_than_week_ago(): void
    {
        Message::query()->create([
            'name' => 'Welcome Basic',
            'slug' => Message::SLUG_WELCOME_BASIC,
            'text' => 'Базовое сообщение',
        ]);

        Message::query()->create([
            'name' => 'Welcome Extended',
            'slug' => Message::SLUG_WELCOME_EXTENDED,
            'text' => 'Расширенное сообщение',
        ]);

        User::query()->create([
            'name' => 'Bob',
            'telegram' => '@bob',
            'telegram_id' => '313131',
            'balance' => 100,
            'is_active' => true,
            'welcome_text_seen_at' => now()->subDays(3),
        ]);

        $this->getJson('/api/users/313131/registration-status')
            ->assertOk()
            ->assertExactJson([
                'registered' => true,
                'active_subscription_end_date' => null,
                'has_money_for_next_subscription_month' => false,
                'welcome_text' => 'Базовое сообщение',
            ]);
    }
}
