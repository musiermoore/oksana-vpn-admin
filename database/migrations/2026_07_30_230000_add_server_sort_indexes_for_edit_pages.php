<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xray_inbounds', function (Blueprint $table): void {
            $table->index(
                ['server_id', 'sort_order', 'external_id', 'id'],
                'xray_inbounds_server_sort_external_id_id_idx'
            );
        });

        Schema::table('proxies', function (Blueprint $table): void {
            $table->index(
                ['server_id', 'sort_order', 'id'],
                'proxies_server_sort_id_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('proxies', function (Blueprint $table): void {
            $table->dropIndex('proxies_server_sort_id_idx');
        });

        Schema::table('xray_inbounds', function (Blueprint $table): void {
            $table->dropIndex('xray_inbounds_server_sort_external_id_id_idx');
        });
    }
};
