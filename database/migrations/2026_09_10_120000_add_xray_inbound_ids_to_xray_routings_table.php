<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xray_routings', function (Blueprint $table): void {
            $table->json('xray_inbound_ids')
                ->nullable()
                ->after('subscription_types');
            $table->json('external_subscription_config_ids')
                ->nullable()
                ->after('xray_inbound_ids');
        });
    }

    public function down(): void
    {
        Schema::table('xray_routings', function (Blueprint $table): void {
            $table->dropColumn(['xray_inbound_ids', 'external_subscription_config_ids']);
        });
    }
};
