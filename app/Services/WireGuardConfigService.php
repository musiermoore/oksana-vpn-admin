<?php

namespace App\Services;

use App\Models\Config;
use App\Models\Server;
use Exception;
use File;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\Laravel\Facades\Telegram;

class WireGuardConfigService
{
    public const WG_CREATE_CONFIG_FILE = 'create-wg-config.sh';
    public const WG_DELETE_CONFIG_FILE = 'delete-wg-config.sh';

    public const WG_SET_LIMIT_FILE = 'set-wg-limit.sh';
    public const WG_REMOVE_LIMIT_FILE = 'remove-wg-limit.sh';

    private Server $server;
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->server = $config->server;
    }

    public static function instance(Config $config): WireGuardConfigService
    {
        return new self($config);
    }

    public function create(): bool
    {
        try {
            return $this->runFile(self::WG_CREATE_CONFIG_FILE, [$this->config->name]);
        } catch (Exception $exception) {
            return false;
        }
    }

    public function delete(): bool
    {
        try {
            return ! $this->fileExists($this->config->name) || $this->runFile(self::WG_DELETE_CONFIG_FILE, [$this->config->name]);
        } catch (Exception $exception) {
            return false;
        }
    }

    public function fileExists($file): bool
    {
        $command = "{$this->server->ssh_command} '[ -f /opt/beget/wireguard/clients/$file.conf ] && echo \"File exists\" || echo \"false\"'";

        exec($command, $output, $result);

        return $result === 0 && in_array('File exists', $output);
    }

    public function setLimit(int|string $limit): bool
    {
        try {
            return $this->runFile(self::WG_SET_LIMIT_FILE, [str_replace('/24', '', $this->config->address), $limit]);
        } catch (Exception $exception) {
            return false;
        }
    }

    public function removeLimit(int|string $limit): bool
    {
        try {
            return $this->runFile(self::WG_REMOVE_LIMIT_FILE, [str_replace('/24', '', $this->config->address), $limit]);
        } catch (Exception $exception) {
            Telegram::sendMessage([
                'chat_id' => "-4543488848",
                'text' => $exception->getMessage()
            ]);
            return false;
        }
    }

    private function runFile($file, array $params = []): bool
    {
        if (config('app.env') !== 'production') {
            return true;
        }

        $inlineParams = implode(' ', $params);

        $command = "{$this->server->ssh_command} {$this->server->app_path}/$file $inlineParams";

        exec($command, $output, $result);

        return $result === 0;
    }
}
