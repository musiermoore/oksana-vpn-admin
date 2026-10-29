<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('xray_inbounds')
            ->select(['id', 'params'])
            ->whereNotNull('params')
            ->orderBy('id')
            ->chunkById(500, function ($inbounds): void {
                foreach ($inbounds as $inbound) {
                    $params = json_decode((string) $inbound->params, true);

                    if (! is_array($params)) {
                        continue;
                    }

                    $originalParams = $params;
                    unset($params['configs'], $params['clientStats'], $params['client_stats']);

                    if ($params === $originalParams) {
                        continue;
                    }

                    DB::table('xray_inbounds')
                        ->where('id', $inbound->id)
                        ->update(['params' => json_encode($params, JSON_THROW_ON_ERROR)]);
                }
            });
    }

    public function down(): void
    {
        // Removed panel snapshots cannot be reconstructed from the remaining inbound definition.
    }
};
