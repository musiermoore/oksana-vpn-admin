<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PromoCodeAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_promo_codes_and_view_archived_history(): void
    {
        $this->withoutVite();
        config()->set('inertia.pages.paths', [resource_path('js/Pages')]);

        $admin = User::factory()->create(['is_admin' => true]);
        $payload = ['code' => '  welcome-10  ', 'benefit' => 'percentage_discount', 'value' => '10.50'];

        $this->actingAs($admin)->get('/promo-codes/create')->assertOk();
        $this->post('/promo-codes', $payload)->assertRedirect('/promo-codes');

        $promo = PromoCode::query()->sole();
        $this->assertSame('WELCOME-10', $promo->code);
        $this->assertSame(10.5, $promo->value);
        $this->assertTrue($promo->once_per_user);
        $this->assertTrue($promo->is_active);
        $this->assertNull($promo->starts_on);
        $this->assertNull($promo->ends_on);

        $this->postJson('/promo-codes', $payload)->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->get("/promo-codes/{$promo->id}/edit")->assertOk();

        $this->put("/promo-codes/{$promo->id}", [
            ...$payload,
            'benefit' => 'bonus_days',
            'value' => 5,
            'once_per_user' => false,
            'new_users_only' => true,
            'is_active' => false,
            'starts_on' => '2026-11-19',
            'ends_on' => '2026-11-22',
        ])->assertRedirect('/promo-codes');

        $this->get('/promo-codes')->assertInertia(fn (Assert $page) => $page
            ->component('PromoCodes/Index')
            ->where('promo_codes.data.0.benefit', 'bonus_days')
            ->where('promo_codes.data.0.once_per_user', false)
            ->where('promo_codes.data.0.new_users_only', true)
            ->where('promo_codes.data.0.is_active', false)
            ->where('promo_codes.data.0.redeemed_count', 0));

        $this->delete("/promo-codes/{$promo->id}")->assertRedirect('/promo-codes');
        $this->assertSoftDeleted($promo);
        $this->get("/promo-codes/{$promo->id}/edit")->assertNotFound();
        $this->get("/promo-codes/{$promo->id}")->assertInertia(fn (Assert $page) => $page
            ->component('PromoCodes/History')
            ->where('promo_code.is_archived', true));
    }

    public function test_admin_validation_checks_dates_and_benefit_value(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->postJson('/promo-codes', [
            'code' => 'TEST',
            'benefit' => 'percentage_discount',
            'value' => 100.01,
            'starts_on' => '2026-11-20',
            'ends_on' => '2026-11-19',
        ])->assertUnprocessable()->assertJsonValidationErrors(['ends_on', 'value']);

        $this->postJson('/promo-codes', [
            'code' => 'TEST',
            'benefit' => 'bonus_days',
            'value' => 1.5,
        ])->assertUnprocessable()->assertJsonValidationErrors('value');
    }

    public function test_customer_cannot_manage_promo_codes(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/promo-codes')->assertForbidden();

        $this->postJson('/promo-codes', [
            'code' => 'CUSTOMER',
            'benefit' => 'fixed_discount',
            'value' => 10,
        ])->assertForbidden();
    }
}
