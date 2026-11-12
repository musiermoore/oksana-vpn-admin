<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ConfigGroup;
use App\Models\VlessConfig;
use App\Models\VlessExternalSubscription;
use App\Models\XrayCustomConfig;

final class ConfigGroupItemRepository
{
    public function findGroupOrFail(int $groupId): ConfigGroup
    {
        return ConfigGroup::query()->findOrFail($groupId);
    }

    public function sync(
        VlessConfig|VlessExternalSubscription|XrayCustomConfig $item,
        ?ConfigGroup $group,
    ): void {
        $item->configGroups()->sync($group === null ? [] : [$group->id]);
    }
}
