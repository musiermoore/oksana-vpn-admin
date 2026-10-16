<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_profile_health_checks', function (Blueprint $table): void {
            $table->id();
            $table->string('flow', 32);
            $table->string('profile_key', 191);
            $table->string('name');
            $table->string('status', 24)->default('unknown');
            $table->json('results')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['flow', 'profile_key']);
            $table->index(['flow', 'status', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_profile_health_checks');
    }
};
