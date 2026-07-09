<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_request_logs', function (Blueprint $table) {
            $table->text('forwarded_for')->nullable()->after('ip_address');
            $table->text('user_agent')->nullable()->after('forwarded_for');
        });
    }

    public function down(): void
    {
        Schema::table('api_request_logs', function (Blueprint $table) {
            $table->dropColumn(['forwarded_for', 'user_agent']);
        });
    }
};
