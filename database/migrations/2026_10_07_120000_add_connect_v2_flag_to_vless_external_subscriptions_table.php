<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vless_external_subscriptions', function (Blueprint $table): void {
            $table->boolean('include_in_connect_v2')->default(true)->after('include_in_whitelist');
        });
    }

    public function down(): void
    {
        Schema::table('vless_external_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('include_in_connect_v2');
        });
    }
};
