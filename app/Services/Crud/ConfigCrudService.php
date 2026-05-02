<?php

namespace App\Services\Crud;

use App\DTOs\Config\ConfigBulkStoreData;
use App\DTOs\Config\ConfigStoreData;
use App\DTOs\Config\ConfigUpdateData;
use App\Models\Config;
use App\Models\Server;
use App\Models\User;
use App\Repositories\ConfigRepository;
use App\Repositories\UserRepository;
use Exception;
use RuntimeException;

class ConfigCrudService
{
    public function __construct(
        private readonly ConfigRepository $configs,
        private readonly UserRepository $users,
    ) {}

    /**
     * @return array<int, string>
     */
    public function createMany(ConfigStoreData $data): array
    {
        $user = $this->users->findOrFail($data->userId);
        $failedConfigs = [];

        foreach ($data->configs as $config) {
            if (! $user->createConfig($config->toArray())) {
                $failedConfigs[] = $config->name;
            }
        }

        return $failedConfigs;
    }

    /**
     * @return array<int, string>
     */
    public function createBulk(ConfigBulkStoreData $data): array
    {
        $server = Server::query()->findOrFail($data->serverId);

        $users = User::query()
            ->with('configs')
            ->whereDoesntHave('configs', function ($query) use ($server) {
                $query->where('server_id', '=', $server->id);
            })
            ->get();

        $failedConfigs = [];

        foreach ($users as $user) {
            $configName = str_replace('@', '', $user->telegram).'_'.$server->code;

            if (! $user->createConfig([
                'name' => $configName,
                'server_id' => $server->id,
            ])) {
                $failedConfigs[] = $configName;
            }
        }

        return $failedConfigs;
    }

    public function update(Config $config, ConfigUpdateData $data): Config
    {
        return $this->configs->update($config, $data->toArray());
    }

    public function delete(Config $config): void
    {
        try {
            if (! $config->deleteWgConfig()) {
                throw new RuntimeException('Ошибка при удалении конфига');
            }

            $this->configs->delete($config);
        } catch (Exception $exception) {
            report($exception);
            throw new RuntimeException('Ошибка при удалении конфига');
        }
    }

    public function enable(Config $config): void
    {
        try {
            if (! $config->enableWgConfig()) {
                throw new RuntimeException('Ошибка при включении конфига');
            }

            $this->configs->update($config, ['is_active' => true]);
        } catch (Exception $exception) {
            report($exception);
            throw new RuntimeException('Ошибка при включении конфига');
        }
    }

    public function disable(Config $config): void
    {
        try {
            if (! $config->disableWgConfig()) {
                throw new RuntimeException('Ошибка при отключении конфига');
            }

            $this->configs->update($config, ['is_active' => false]);
        } catch (Exception $exception) {
            report($exception);
            throw new RuntimeException('Ошибка при отключении конфига');
        }
    }
}
