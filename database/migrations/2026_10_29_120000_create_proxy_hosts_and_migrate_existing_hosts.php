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
        Schema::create('proxy_hosts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proxy_id')->constrained()->cascadeOnDelete();
            $table->string('host');
            $table->unsignedInteger('port')->default(443);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['proxy_id', 'sort']);
        });

        DB::table('proxies')->orderBy('id')->get()->each(function (object $proxy): void {
            DB::table('proxy_hosts')->insert([
                'proxy_id' => $proxy->id,
                'host' => $proxy->host,
                'port' => $proxy->port,
                'sort' => 0,
                'is_active' => true,
                'created_at' => $proxy->created_at,
                'updated_at' => $proxy->updated_at,
            ]);
        });

        Schema::table('proxies', function (Blueprint $table): void {
            $table->dropColumn(['host', 'port']);
        });
    }

    public function down(): void
    {
        Schema::table('proxies', function (Blueprint $table): void {
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->default(443);
        });

        DB::table('proxy_hosts')
            ->where('sort', 0)
            ->orderBy('id')
            ->get()
            ->each(function (object $host): void {
                DB::table('proxies')->where('id', $host->proxy_id)->update([
                    'host' => $host->host,
                    'port' => $host->port,
                ]);
            });

        Schema::dropIfExists('proxy_hosts');
    }
};
