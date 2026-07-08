<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vless_external_subscriptions', function (Blueprint $table): void {
            $table->string('connect_name_prefix')->nullable()->after('filter_pattern');
        });
    }

    public function down(): void
    {
        Schema::table('vless_external_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('connect_name_prefix');
        });
    }
};
