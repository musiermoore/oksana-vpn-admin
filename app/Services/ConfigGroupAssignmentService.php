<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ConfigGroup;
use App\Models\VlessConfig;
use App\Models\VlessExternalSubscription;
use App\Models\XrayCustomConfig;
use App\Repositories\ConfigGroupItemRepository;
use Illuminate\Support\Facades\DB;

final class ConfigGroupAssignmentService
{
    public function __construct(private readonly ConfigGroupItemRepository $items) {}

    public function assign(
        VlessConfig|VlessExternalSubscription|XrayCustomConfig $item,
        ?int $groupId,
    ): void {
        DB::transaction(function () use ($item, $groupId): void {
            $group = $groupId === null ? null : $this->items->findGroupOrFail($groupId);
            $this->items->sync($item, $group);
        });
    }
}
