<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_types', function (Blueprint $table): void {
            $table->boolean('requires_user_token')->default(true)->after('is_active');
        });

        DB::table('subscription_types')
            ->where('slug', 'free')
            ->update(['requires_user_token' => false]);
    }

    public function down(): void
    {
        Schema::table('subscription_types', function (Blueprint $table): void {
            $table->dropColumn('requires_user_token');
        });
    }
};
