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
        Schema::table('servers', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->after('code');
        });

        Schema::table('vless_external_subscriptions', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->after('name');
        });

        Schema::table('xray_inbounds', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->after('external_id');
        });

        Schema::table('proxies', function (Blueprint $table): void {
            $table->foreignId('server_id')
                ->nullable()
                ->after('xray_inbound_id')
                ->constrained('servers')
                ->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0)->after('server_id');
        });

        Schema::table('proxy_server', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->after('server_id');
        });

        $this->backfillServerSortOrder();
        $this->backfillExternalSubscriptionSortOrder();
        $this->backfillInboundSortOrder();
        $this->backfillProxyServerRelation();
        $this->backfillProxyPivotSortOrder();
        $this->backfillProxySortOrder();
    }

    public function down(): void
    {
        Schema::table('proxy_server', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });

        Schema::table('xray_inbounds', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });

        Schema::table('vless_external_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });

        Schema::table('proxies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('server_id');
            $table->dropColumn('sort_order');
        });

        Schema::table('servers', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });
    }

    private function backfillServerSortOrder(): void
    {
        DB::table('servers')
            ->orderBy('id')
            ->get(['id'])
            ->values()
            ->each(fn (object $server, int $index) => DB::table('servers')
                ->where('id', $server->id)
                ->update(['sort_order' => $index]));
    }

    private function backfillExternalSubscriptionSortOrder(): void
    {
        DB::table('vless_external_subscriptions')
            ->orderByDesc('id')
            ->get(['id'])
            ->values()
            ->each(fn (object $subscription, int $index) => DB::table('vless_external_subscriptions')
                ->where('id', $subscription->id)
                ->update(['sort_order' => $index]));
    }

    private function backfillInboundSortOrder(): void
    {
        DB::table('xray_inbounds')
            ->orderBy('server_id')
            ->orderBy('external_id')
            ->orderBy('id')
            ->get(['id', 'server_id'])
            ->groupBy('server_id')
            ->each(function ($rows): void {
                collect($rows)
                    ->values()
                    ->each(fn (object $row, int $index) => DB::table('xray_inbounds')
                        ->where('id', $row->id)
                        ->update(['sort_order' => $index]));
            });
    }

    private function backfillProxyServerRelation(): void
    {
        DB::table('proxy_server')
            ->orderBy('proxy_id')
            ->orderBy('server_id')
            ->orderBy('id')
            ->get(['proxy_id', 'server_id'])
            ->groupBy('proxy_id')
            ->each(function ($rows): void {
                $firstRow = collect($rows)->first();
                $serverId = (int) ($firstRow->server_id ?? 0);
                $proxyId = (int) ($firstRow->proxy_id ?? 0);

                if ($serverId < 1 || $proxyId < 1) {
                    return;
                }

                DB::table('proxies')
                    ->where('id', $proxyId)
                    ->update(['server_id' => $serverId]);
            });
    }

    private function backfillProxyPivotSortOrder(): void
    {
        DB::table('proxy_server')
            ->orderBy('server_id')
            ->orderBy('id')
            ->get(['id', 'server_id'])
            ->groupBy('server_id')
            ->each(function ($rows): void {
                collect($rows)
                    ->values()
                    ->each(fn (object $row, int $index) => DB::table('proxy_server')
                        ->where('id', $row->id)
                        ->update(['sort_order' => $index]));
            });
    }

    private function backfillProxySortOrder(): void
    {
        DB::table('proxies')
            ->whereNotNull('server_id')
            ->orderBy('server_id')
            ->orderBy('id')
            ->get(['id', 'server_id'])
            ->groupBy('server_id')
            ->each(function ($rows): void {
                collect($rows)
                    ->values()
                    ->each(fn (object $row, int $index) => DB::table('proxies')
                        ->where('id', $row->id)
                        ->update(['sort_order' => $index]));
            });
    }
};
