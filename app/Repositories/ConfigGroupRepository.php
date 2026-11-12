<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ConfigGroup;
use Illuminate\Database\Eloquent\Collection;

final class ConfigGroupRepository
{
    /** @return Collection<int, ConfigGroup> */
    public function ordered(): Collection
    {
        return ConfigGroup::query()->ordered()->get(['id', 'name', 'sort', 'is_default']);
    }

    public function nextSort(): int
    {
        return ((int) ConfigGroup::query()->max('sort')) + 1;
    }

    public function create(array $attributes): ConfigGroup
    {
        return ConfigGroup::query()->create($attributes);
    }

    public function update(ConfigGroup $group, array $attributes): ConfigGroup
    {
        $group->update($attributes);

        return $group->refresh();
    }

    public function clearDefaultExcept(?int $groupId = null): void
    {
        ConfigGroup::query()
            ->when($groupId !== null, fn ($query) => $query->where('id', '!=', $groupId))
            ->update(['is_default' => false]);
    }

    public function delete(ConfigGroup $group): void
    {
        $group->delete();
    }
}
