<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MANAGED_SOURCES = ['manual_global', 'roscomvpn_json'];

    public function up(): void
    {
        DB::table('xray_routings')
            ->whereIn('source', self::MANAGED_SOURCES)
            ->get(['id', 'subscription_types'])
            ->each(function (object $routing): void {
                $types = json_decode((string) $routing->subscription_types, true);

                if (! is_array($types)) {
                    return;
                }

                $types = array_values(array_unique([
                    ...$types,
                    'connect_v2',
                    'external',
                ]));

                DB::table('xray_routings')
                    ->where('id', $routing->id)
                    ->update(['subscription_types' => json_encode($types, JSON_THROW_ON_ERROR)]);
            });
    }

    public function down(): void
    {
        DB::table('xray_routings')
            ->whereIn('source', self::MANAGED_SOURCES)
            ->get(['id', 'subscription_types'])
            ->each(function (object $routing): void {
                $types = json_decode((string) $routing->subscription_types, true);

                if (! is_array($types)) {
                    return;
                }

                $types = array_values(array_diff($types, ['connect_v2', 'external']));

                DB::table('xray_routings')
                    ->where('id', $routing->id)
                    ->update(['subscription_types' => json_encode($types, JSON_THROW_ON_ERROR)]);
            });
    }
};
