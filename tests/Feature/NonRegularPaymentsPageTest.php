<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NonRegularPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NonRegularPaymentsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_delete_a_non_regular_payment(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post('/non-regular-payments', [
                'amount' => '125.50',
                'description' => 'Change server IP',
            ])
            ->assertRedirect('/non-regular-payments');

        $payment = NonRegularPayment::query()->sole();

        $this->assertSame(125.5, (float) $payment->amount);
        $this->assertSame('Change server IP', $payment->description);

        $this->actingAs($admin)
            ->delete('/non-regular-payments/'.$payment->id)
            ->assertRedirect('/non-regular-payments');

        $this->assertDatabaseMissing('non_regular_payments', ['id' => $payment->id]);
    }
}
