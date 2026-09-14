<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogViewerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_log_viewer(): void
    {
        $this->get('/log-viewer')
            ->assertForbidden();
    }

    public function test_non_admin_cannot_view_log_viewer(): void
    {
        $this->actingAs($this->createUser(isAdmin: false))
            ->get('/log-viewer')
            ->assertForbidden();
    }

    public function test_admin_can_view_log_viewer(): void
    {
        $this->actingAs($this->createUser(isAdmin: true))
            ->get('/log-viewer')
            ->assertOk();
    }

    public function test_non_admin_cannot_access_log_viewer_api(): void
    {
        $this->actingAs($this->createUser(isAdmin: false))
            ->get('/log-viewer/api/hosts')
            ->assertForbidden();
    }

    public function test_admin_can_access_log_viewer_api(): void
    {
        $this->actingAs($this->createUser(isAdmin: true))
            ->get('/log-viewer/api/hosts')
            ->assertOk();
    }

    private function createUser(bool $isAdmin): User
    {
        return User::query()->create([
            'name' => $isAdmin ? 'Admin' : 'Client',
            'telegram' => $isAdmin ? '@admin' : '@client',
            'is_admin' => $isAdmin,
        ]);
    }
}
