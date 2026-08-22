<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_search_returns_matching_results(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
            'is_admin' => true,
        ]);

        User::query()->create([
            'name' => 'Alice Search',
            'telegram' => '@alice',
            'is_admin' => false,
        ]);

        Server::query()->create([
            'name' => 'Search Node',
            'code' => 'SEA',
            'ip' => '10.10.10.10',
            'type' => Server::TYPE_WIREGUARD,
            'is_active' => true,
            'is_ready' => true,
        ]);

        $this->actingAs($admin)
            ->get('/search?q=alice')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('query', 'alice')
                ->has('results', 1)
                ->where('results.0.label', 'Пользователи')
                ->where('results.0.items.0.title', '@alice (Alice Search)')
            );
    }
}
