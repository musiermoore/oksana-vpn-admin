<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('giveaway_series', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('auto_repeat_enabled')->default(false);
            $table->unsignedInteger('repeat_delay_minutes')->default(0);
            $table->unsignedInteger('repeat_limit')->nullable();
            $table->timestamps();
        });

        Schema::create('giveaways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->nullable()->constrained('giveaway_series')->nullOnDelete();
            $table->foreignId('parent_giveaway_id')->nullable()->constrained('giveaways')->nullOnDelete();
            $table->unsignedInteger('sequence_number')->default(1);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('duration_minutes');
            $table->timestamps();

            $table->unique(['series_id', 'sequence_number']);
            $table->unique('parent_giveaway_id');
            $table->index(['status', 'starts_at']);
            $table->index(['status', 'ends_at']);
        });

        Schema::create('giveaway_prizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('giveaway_id')->constrained('giveaways')->cascadeOnDelete();
            $table->unsignedSmallInteger('duration_months');
            $table->unsignedInteger('quantity')->default(0);
            $table->string('title')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['giveaway_id', 'sort_order']);
        });

        Schema::create('giveaway_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('giveaway_id')->constrained('giveaways')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('joined_at');
            $table->unsignedInteger('weight_at_draw')->nullable();
            $table->unsignedInteger('eligible_referrals_count_at_draw')->nullable();
            $table->dateTime('snapshot_taken_at')->nullable();
            $table->timestamps();

            $table->unique(['giveaway_id', 'user_id']);
            $table->index(['giveaway_id', 'joined_at']);
        });

        Schema::create('giveaway_winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('giveaway_id')->constrained('giveaways')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('giveaway_prize_id')->nullable()->constrained('giveaway_prizes')->nullOnDelete();
            $table->unsignedInteger('prize_slot');
            $table->unsignedSmallInteger('duration_months');
            $table->unsignedInteger('weight_at_draw');
            $table->unsignedInteger('eligible_referrals_count_at_draw');
            $table->dateTime('selected_at');
            $table->string('prize_status')->default('pending');
            $table->dateTime('prize_granted_at')->nullable();
            $table->text('prize_error')->nullable();
            $table->timestamps();

            $table->unique(['giveaway_id', 'user_id']);
            $table->unique(['giveaway_id', 'prize_slot']);
            $table->index(['giveaway_id', 'prize_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('giveaway_winners');
        Schema::dropIfExists('giveaway_participants');
        Schema::dropIfExists('giveaway_prizes');
        Schema::dropIfExists('giveaways');
        Schema::dropIfExists('giveaway_series');
    }
};
