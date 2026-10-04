<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xray_routing_geodata', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('geoip_url')->nullable();
            $table->text('geosite_url')->nullable();
            $table->json('assets')->nullable();
            $table->string('last_updated')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'id']);
        });

        Schema::create('xray_routing_dns_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('servers');
            $table->string('query_strategy')->default('UseIPv4');
            $table->boolean('enable_parallel_query')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'id']);
        });

        Schema::create('xray_custom_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('dns_settings_id')->nullable()->constrained('xray_routing_dns_settings')->nullOnDelete();
            $table->foreignId('geodata_id')->nullable()->constrained('xray_routing_geodata')->nullOnDelete();
            $table->json('xray_inbound_ids')->nullable();
            $table->json('external_subscription_config_ids')->nullable();
            $table->json('proxy_ids')->nullable();
            $table->json('xray_routing_ids')->nullable();
            $table->json('base_settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xray_custom_configs');
        Schema::dropIfExists('xray_routing_dns_settings');
        Schema::dropIfExists('xray_routing_geodata');
    }
};
