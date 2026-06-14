<?php

namespace App\Services\Crud;

use App\DTOs\Config\ConfigBulkStoreData;
use App\DTOs\Config\ConfigStoreData;
use App\DTOs\Config\ConfigUpdateData;
use App\Models\Config;
use App\Models\Server;
use App\Models\User;
use App\Repositories\ConfigRepository;
use App\Repositories\ServerRepository;
use App\Repositories\UserRepository;
use Exception;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use RuntimeException;

class ConfigCrudService
{
    public function __construct(
        private readonly ConfigRepository $configs,
        private readonly ServerRepository $servers,
        private readonly UserRepository $users,
    ) {}

    /**
     * @return array<int, string>
     */
    public function createMany(ConfigStoreData $data): array
    {
        $user = $this->users->findOrFail($data->userId);
        $servers = $this->servers
            ->findByIds(array_values(array_unique(array_map(
                fn ($config) => $config->serverId,
                $data->configs,
            ))))
            ->keyBy('id');
        $failedConfigs = [];

        foreach ($data->configs as $config) {
            /** @var Server $server */
            $server = $servers->get($config->serverId) ?? $this->servers->findOrFail($config->serverId);
            $configName = $this->generateConfigName($user, $server);

            if (! $user->createConfig([
                'name' => $configName,
                'description' => $config->description,
                'server_id' => $server->id,
            ])) {
                $failedConfigs[] = $configName;
            }
        }

        return $failedConfigs;
    }

    /**
     * @return array<int, string>
     */
    public function createBulk(ConfigBulkStoreData $data): array
    {
        $server = $this->servers->findOrFail($data->serverId);

        $users = User::query()
            ->with('configs')
            ->whereDoesntHave('configs', function ($query) use ($server) {
                $query->where('server_id', '=', $server->id);
            })
            ->get();

        $failedConfigs = [];
        $lastIndex = $users->count() - 1;

        foreach ($users as $index => $user) {
            $configName = $this->generateConfigName($user, $server);

            if (! $user->createConfig([
                'name' => $configName,
                'server_id' => $server->id,
            ])) {
                $failedConfigs[] = $configName;
            }

            if ($index < $lastIndex) {
                Sleep::sleep(5);
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

    private function generateConfigName(User $user, Server $server): string
    {
        $userPart = Str::slug(trim((string) $user->telegram, '@'));
        $userPart = $userPart !== '' ? $userPart : (string) $user->telegram_id;

        $serverPart = Str::slug($server->name);
        $serverPart = $serverPart !== '' ? $serverPart : Str::slug($server->code);
        $serverPart = $serverPart !== '' ? $serverPart : 'server';

        $name = implode('-', [
            $userPart,
            $serverPart,
            $this->generateAlphabeticString(16),
        ]);

        if (Config::whereName($name)->exists()) {
            return $this->generateConfigName($user, $server);
        }

        return $name;
    }

    private function generateAlphabeticString(int $length): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz';
        $value = '';

        for ($i = 0; $i < $length; $i++) {
            $value .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $value;
    }
}
