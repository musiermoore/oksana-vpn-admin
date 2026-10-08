<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('xray_inbounds')) {
            return;
        }

        DB::table('xray_inbounds')
            ->select(['id', 'params'])
            ->whereNotNull('params')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $params = json_decode((string) $row->params, true);

                    if (! is_array($params) || ! array_key_exists('settings', $params)) {
                        continue;
                    }

                    $settings = $params['settings'];

                    if (is_string($settings)) {
                        $settings = json_decode($settings, true);
                    }

                    if (! is_array($settings) || ! array_key_exists('clients', $settings)) {
                        continue;
                    }

                    unset($settings['clients']);
                    $params['settings'] = $settings;

                    DB::table('xray_inbounds')
                        ->where('id', $row->id)
                        ->update(['params' => json_encode($params, JSON_THROW_ON_ERROR)]);
                }
            });
    }

    public function down(): void
    {
        // Client data is owned by vless_configs and cannot be restored here.
    }
};
