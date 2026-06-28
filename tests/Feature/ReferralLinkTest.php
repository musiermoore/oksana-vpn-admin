<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_link_uses_startapp_parameter_for_mini_app_launch(): void
    {
        config()->set('services.telegram.bot_username', 'OksanaVpnBot');

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
        ]);

        $link = app(ReferralService::class)->buildReferralLink($user);

        $this->assertSame(
            'https://t.me/OksanaVpnBot?startapp=ref_'.$user->id,
            $link
        );
    }
}
