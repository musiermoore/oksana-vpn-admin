<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxy_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('proxies', function (Blueprint $table): void {
            $table->foreignId('proxy_group_id')->nullable()->after('name')->constrained('proxy_groups')->nullOnDelete();
        });

        Schema::table('proxy_hosts', function (Blueprint $table): void {
            $table->foreignId('proxy_server_id')->nullable()->after('proxy_id')->constrained('servers')->nullOnDelete();
            $table->boolean('is_https')->default(false)->after('port');
        });
    }

    public function down(): void
    {
        Schema::table('proxy_hosts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('proxy_server_id');
            $table->dropColumn('is_https');
        });

        Schema::table('proxies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('proxy_group_id');
        });

        Schema::dropIfExists('proxy_groups');
    }
};
