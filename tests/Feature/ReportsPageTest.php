<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Server;
use App\Models\ServerPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_shows_financial_summary_for_selected_period(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
        ]);

        Invoice::query()->create([
            'user_id' => $admin->id,
            'provider' => 'manual',
            'provider_payment_id' => 'reports-main-invoice-1',
            'status' => 'paid',
            'tax_status' => Invoice::TAX_STATUS_SENT,
            'paid' => true,
            'amount' => 1000,
            'currency' => 'RUB',
            'description' => 'Main invoice',
            'paid_at' => '2026-07-10 12:00:00',
        ]);

        Invoice::query()->create([
            'user_id' => $admin->id,
            'provider' => 'manual',
            'provider_payment_id' => 'reports-draft-invoice-1',
            'status' => 'pending',
            'tax_status' => Invoice::TAX_STATUS_NOT_SENT,
            'paid' => false,
            'amount' => 500,
            'currency' => 'RUB',
            'description' => 'Draft invoice',
            'created_at' => '2026-07-18 08:00:00',
            'updated_at' => '2026-07-18 08:00:00',
        ]);

        $deletedServer = Server::query()->create([
            'name' => 'Deleted Node',
            'code' => 'DEL-1',
            'sort_order' => 1,
            'ip' => '10.0.0.1',
            'type' => Server::TYPE_VLESS,
        ]);
        $deletedServer->delete();

        ServerPrice::query()->create([
            'server_id' => $deletedServer->id,
            'effective_from' => '2026-07-01',
            'price' => 310,
        ]);

        $activeServer = Server::query()->create([
            'name' => 'Active Node',
            'code' => 'ACT-1',
            'sort_order' => 2,
            'ip' => '10.0.0.2',
            'type' => Server::TYPE_VLESS,
        ]);

        ServerPrice::query()->create([
            'server_id' => $activeServer->id,
            'effective_from' => '2026-07-16',
            'price' => 620,
        ]);

        $this->actingAs($admin)
            ->get('/reports?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->where('filters.date_from', '2026-07-01')
                ->where('filters.date_to', '2026-07-31')
                ->where('summary.revenue', 1000)
                ->where('summary.total_server_costs', 630)
                ->where('summary.estimated_taxes', 40)
                ->where('summary.net_profit', 330)
                ->where('summary.margin_percent', 33)
                ->where('summary.paid_invoices_count', 1)
                ->where('top_servers.0.name', 'Active Node')
                ->where('top_servers.0.total_cost', 320)
                ->where('top_servers.1.name', 'Deleted Node')
                ->where('top_servers.1.total_cost', 310)
                ->where('top_servers.1.is_deleted', true)
            );
    }
}
