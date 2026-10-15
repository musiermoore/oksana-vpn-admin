<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xray_inbound_health_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('xray_inbound_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('unknown');
            $table->uuid('probe_client_id')->nullable();
            $table->string('probe_client_email')->nullable();
            $table->json('results')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xray_inbound_health_checks');
    }
};
