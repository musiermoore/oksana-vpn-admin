<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\XrayCustomConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XrayCustomConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_form_can_disable_a_custom_config(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $config = XrayCustomConfig::query()->create([
            'name' => 'Custom config',
            'slug' => 'custom-config',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->put(route('xray-custom-configs.update', $config), [
                'name' => $config->name,
                'slug' => $config->slug,
                'description' => null,
                'xray_inbound_ids' => [],
                'external_subscription_config_ids' => [],
                'external_subscription_ids' => [],
                'proxy_ids' => [],
                'xray_routing_ids' => [],
                'base_settings_json' => '{}',
                'outbound_groups_json' => '[]',
                'routes_json' => '[]',
                'is_active' => false,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $this->assertFalse($config->fresh()->is_active);
    }
}
