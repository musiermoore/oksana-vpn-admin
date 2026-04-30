<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('price');
            $table->timestamps();

            $table->unique(['user_id', 'start_date', 'end_date']);
        });

        $activePeriod = DB::table('current_payments')
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->orderByDesc('start_date')
            ->first();

        if (! $activePeriod) {
            return;
        }

        $timestamp = now();

        $subscriptions = DB::table('users')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->select('id')
            ->get()
            ->map(fn ($user) => [
                'user_id' => $user->id,
                'start_date' => $activePeriod->start_date,
                'end_date' => $activePeriod->end_date,
                'price' => $activePeriod->amount,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])
            ->all();

        if (empty($subscriptions)) {
            return;
        }

        DB::table('user_subscriptions')->insert($subscriptions);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
