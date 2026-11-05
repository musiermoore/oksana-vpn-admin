<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vless_external_subscriptions', function (Blueprint $table): void {
            $table->foreignId('geodata_id')
                ->nullable()
                ->after('connect_name_prefix')
                ->constrained('xray_routing_geodata')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vless_external_subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('geodata_id');
        });
    }
};
