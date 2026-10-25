<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\XrayCustomConfig\XrayCustomConfigUserPriceData;
use App\Models\User;
use App\Models\XrayCustomConfigUserPrice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class XrayCustomConfigUserPriceService
{
    public function store(User $user, XrayCustomConfigUserPriceData $data): XrayCustomConfigUserPrice
    {
        return DB::transaction(function () use ($user, $data): XrayCustomConfigUserPrice {
            $assignment = $user->xrayCustomConfigAssignments()->firstOrCreate([
                'xray_custom_config_id' => $data->xrayCustomConfigId,
            ]);

            return $assignment->prices()->create([
                'price' => $data->price,
                'effective_from' => CarbonImmutable::createFromFormat('!Y-m-d', $data->effectiveFrom),
            ]);
        });
    }
}
