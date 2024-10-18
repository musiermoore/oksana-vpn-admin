<?php

use App\Models\Limit;
use App\Models\Config;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 300);

        $configs = Config::all();

        $limits = [];
        foreach ($configs as $config) {
            try {
                $result = $config->setSpeedLimit(30);

                if ($result) {
                    $limits[] = [
                        'config_id' => $config->id,
                        'amount' => 30
                    ];
                }
            } catch (Exception $exception) {
                dump($exception->getMessage());
            }
        }

        Limit::insert($limits);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('limits')->delete();
    }
};
