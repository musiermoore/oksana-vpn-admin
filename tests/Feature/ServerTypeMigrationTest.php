<?php

namespace Tests\Feature;

use App\Models\Server;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ServerTypeMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_backfills_type_from_is_vless(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::drop('servers');
        Schema::enableForeignKeyConstraints();

        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->string('ip');
            $table->boolean('is_https')->default(false);
            $table->string('link_host')->nullable();
            $table->string('panel_link')->nullable();
            $table->string('panel_username')->nullable();
            $table->text('panel_password')->nullable();
            $table->string('panel_api_version')->default(Server::PANEL_API_V2_9);
            $table->string('app_path')->default('/opt/app');
            $table->text('ssh_private_key')->nullable();
            $table->text('ssh_public_key')->nullable();
            $table->boolean('is_vless')->default(false);
            $table->boolean('is_ready')->default(false);
            $table->json('allowed_inbound_ids')->nullable();
            $table->timestamps();
        });

        DB::table('servers')->insert([
            [
                'id' => 1,
                'name' => 'Legacy WG',
                'code' => 'LWG',
                'ip' => '10.0.0.1',
                'is_vless' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'VLESS',
                'code' => 'VLS',
                'ip' => '10.0.0.2',
                'is_vless' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migration = require database_path('migrations/2026_06_13_120000_add_type_to_servers_table.php');
        $migration->up();

        $this->assertDatabaseHas('servers', [
            'id' => 1,
            'type' => Server::TYPE_WIREGUARD_OLD,
        ]);

        $this->assertDatabaseHas('servers', [
            'id' => 2,
            'type' => Server::TYPE_VLESS,
        ]);
    }
}
