<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConfigGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigGroupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_one_config_group_can_be_default_and_sort_is_saved(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->post(route('config-groups.store'), [
            'name' => 'Auto Wi-Fi',
            'sort' => 10,
            'is_default' => true,
        ])->assertSessionHasNoErrors();
        $first = ConfigGroup::query()->where('name', 'Auto Wi-Fi')->firstOrFail();

        $this->post(route('config-groups.store'), [
            'name' => 'Auto LTE',
            'sort' => 20,
            'is_default' => true,
        ])->assertSessionHasNoErrors();
        $second = ConfigGroup::query()->where('name', 'Auto LTE')->firstOrFail();

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->is_default);
        $this->assertSame(20, $second->sort);

        $this->patch(route('config-groups.update', $first), [
            'name' => 'Auto Wi-Fi',
            'sort' => 5,
            'is_default' => true,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($first->fresh()->is_default);
        $this->assertFalse($second->fresh()->is_default);
        $this->assertSame(5, $first->fresh()->sort);
    }
}
