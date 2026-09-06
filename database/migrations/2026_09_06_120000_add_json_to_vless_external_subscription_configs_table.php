<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vless_external_subscription_configs', function (Blueprint $table): void {
            $table->json('json')->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('vless_external_subscription_configs', function (Blueprint $table): void {
            $table->dropColumn('json');
        });
    }
};
