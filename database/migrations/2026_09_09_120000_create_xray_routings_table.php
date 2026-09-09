<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xray_routings', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('source')->nullable();
            $table->string('source_key')->nullable();
            $table->string('outbound', 32);
            $table->json('subscription_types')->nullable();
            $table->json('rules');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order', 'id']);
            $table->index('outbound');
            $table->unique(['source', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xray_routings');
    }
};
