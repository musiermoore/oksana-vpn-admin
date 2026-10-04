<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xray_custom_config_outbound_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('xray_custom_config_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('tag');
            $table->string('strategy')->default('roundRobin');
            $table->foreignId('fallback_group_id')->nullable()->constrained('xray_custom_config_outbound_groups')->nullOnDelete();
            $table->json('xray_inbound_ids')->nullable();
            $table->json('external_subscription_config_ids')->nullable();
            $table->json('proxy_ids')->nullable();
            $table->json('strategy_settings')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['xray_custom_config_id', 'sort_order']);
        });

        Schema::create('xray_custom_config_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('xray_custom_config_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('rules');
            $table->string('target_type', 32);
            $table->string('target_tag')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['xray_custom_config_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xray_custom_config_routes');
        Schema::dropIfExists('xray_custom_config_outbound_groups');
    }
};
