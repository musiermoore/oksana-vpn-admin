<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vless_external_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');
            $table->text('source_url');
            $table->string('filter_pattern')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_ready')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamps();
        });

        Schema::create('vless_external_subscription_configs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('vless_external_subscription_id');
            $table->string('config_key');
            $table->string('name');
            $table->string('normalized_name');
            $table->string('protocol')->nullable();
            $table->text('url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['vless_external_subscription_id', 'config_key'],
                'vless_external_subscription_configs_unique_key'
            );
            $table->foreign('vless_external_subscription_id', 'vless_ext_sub_cfg_sub_id_fk')
                ->references('id')
                ->on('vless_external_subscriptions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vless_external_subscription_configs');
        Schema::dropIfExists('vless_external_subscriptions');
    }
};
