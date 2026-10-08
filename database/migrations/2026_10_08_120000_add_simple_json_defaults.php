<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xray_routing_dns_settings', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false)->after('is_active');
            $table->index(['is_default', 'is_active', 'id']);
        });

        Schema::table('xray_routings', function (Blueprint $table): void {
            $table->boolean('is_global')->default(false)->after('proxy_ids');
            $table->index(['is_global', 'is_active', 'sort_order', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('xray_routings', function (Blueprint $table): void {
            $table->dropIndex(['is_global', 'is_active', 'sort_order', 'id']);
            $table->dropColumn('is_global');
        });

        Schema::table('xray_routing_dns_settings', function (Blueprint $table): void {
            $table->dropIndex(['is_default', 'is_active', 'id']);
            $table->dropColumn('is_default');
        });
    }
};
