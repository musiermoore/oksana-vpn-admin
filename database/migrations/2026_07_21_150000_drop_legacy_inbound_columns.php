<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vless_configs', function (Blueprint $table): void {
            $table->dropIndex(['server_id', 'inbound_id']);
            $table->dropColumn('inbound_id');
        });

        Schema::table('servers', function (Blueprint $table): void {
            $table->dropColumn('allowed_inbound_ids');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->json('allowed_inbound_ids')->nullable()->after('hide_configs_for_non_admins');
        });

        Schema::table('vless_configs', function (Blueprint $table): void {
            $table->unsignedBigInteger('inbound_id')->nullable()->after('server_id');
            $table->index(['server_id', 'inbound_id']);
        });
    }
};
