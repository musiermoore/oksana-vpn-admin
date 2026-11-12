<?php

declare(strict_types=1);

namespace App\Services\Crud;

use App\DTOs\ConfigGroup\ConfigGroupData;
use App\Models\ConfigGroup;
use App\Repositories\ConfigGroupRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class ConfigGroupCrudService
{
    public function __construct(private readonly ConfigGroupRepository $groups) {}

    /** @return Collection<int, ConfigGroup> */
    public function ordered(): Collection
    {
        return $this->groups->ordered();
    }

    public function create(ConfigGroupData $data): ConfigGroup
    {
        return DB::transaction(function () use ($data): ConfigGroup {
            if ($data->isDefault) {
                $this->groups->clearDefaultExcept();
            }

            return $this->groups->create($data->toModelAttributes($this->groups->nextSort()));
        });
    }

    public function update(ConfigGroup $group, ConfigGroupData $data): ConfigGroup
    {
        return DB::transaction(function () use ($group, $data): ConfigGroup {
            if ($data->isDefault) {
                $this->groups->clearDefaultExcept((int) $group->id);
            }

            return $this->groups->update($group, $data->toModelAttributes($data->sort ?? (int) $group->sort));
        });
    }

    public function delete(ConfigGroup $group): void
    {
        $this->groups->delete($group);
    }
}
