<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vless_external_subscriptions', function (Blueprint $table): void {
            $table->string('except_pattern')->nullable()->after('filter_pattern');
        });
    }

    public function down(): void
    {
        Schema::table('vless_external_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('except_pattern');
        });
    }
};
