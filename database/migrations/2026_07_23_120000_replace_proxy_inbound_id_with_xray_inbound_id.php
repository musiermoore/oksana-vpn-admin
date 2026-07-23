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
        Schema::table('proxies', function (Blueprint $table): void {
            $table->foreignId('xray_inbound_id')
                ->nullable()
                ->after('inbound_id')
                ->constrained('xray_inbounds')
                ->nullOnDelete();
        });

        $proxyInboundMappings = DB::table('proxies')
            ->join('proxy_server', 'proxy_server.proxy_id', '=', 'proxies.id')
            ->join('xray_inbounds', function ($join): void {
                $join
                    ->on('xray_inbounds.server_id', '=', 'proxy_server.server_id')
                    ->on('xray_inbounds.external_id', '=', 'proxies.inbound_id');
            })
            ->whereNotNull('proxies.inbound_id')
            ->groupBy('proxies.id')
            ->selectRaw('proxies.id as proxy_id, MIN(xray_inbounds.id) as xray_inbound_id')
            ->get();

        foreach ($proxyInboundMappings as $mapping) {
            DB::table('proxies')
                ->where('id', $mapping->proxy_id)
                ->update([
                    'xray_inbound_id' => $mapping->xray_inbound_id,
                ]);
        }

        Schema::table('proxies', function (Blueprint $table): void {
            $table->dropColumn('inbound_id');
        });
    }

    public function down(): void
    {
        Schema::table('proxies', function (Blueprint $table): void {
            $table->unsignedInteger('inbound_id')->nullable()->after('port');
        });

        $proxyInboundMappings = DB::table('proxies')
            ->join('xray_inbounds', 'xray_inbounds.id', '=', 'proxies.xray_inbound_id')
            ->whereNotNull('proxies.xray_inbound_id')
            ->select('proxies.id as proxy_id', 'xray_inbounds.external_id')
            ->get();

        foreach ($proxyInboundMappings as $mapping) {
            DB::table('proxies')
                ->where('id', $mapping->proxy_id)
                ->update([
                    'inbound_id' => $mapping->external_id,
                ]);
        }

        Schema::table('proxies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('xray_inbound_id');
        });
    }
};
