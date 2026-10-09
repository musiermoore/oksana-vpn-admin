<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xray_custom_config_geodata', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('xray_custom_config_id');
            $table->string('client_key', 32);
            $table->unsignedBigInteger('geodata_id');
            $table->timestamps();
            $table->foreign('xray_custom_config_id', 'xcg_geo_config_fk')
                ->references('id')
                ->on('xray_custom_configs')
                ->cascadeOnDelete();
            $table->foreign('geodata_id', 'xcg_geo_data_fk')
                ->references('id')
                ->on('xray_routing_geodata')
                ->restrictOnDelete();
            $table->unique(['xray_custom_config_id', 'client_key'], 'xcg_geo_client_uq');
            $table->index(['client_key', 'geodata_id'], 'xcg_geo_data_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xray_custom_config_geodata');
    }
};
