<?php

namespace App\Services;

use App\Models\Server;
use Exception;
use Symfony\Component\Process\Process;

class WireGuardConfigService
{
    public const WG_CREATE_CONFIG_FILE = 'create-wg-config.sh';
    public const WG_DELETE_CONFIG_FILE = 'delete-wg-config.sh';

    private Server $server;
    private string $name;

    public function __construct(Server $server, string $name)
    {
        $this->server = $server;
        $this->name = $name;
    }

    public static function instance(Server $server, string $name): WireGuardConfigService
    {
        return new self($server, $name);
    }

    public function create(): bool
    {
        try {
            $this->runFile(self::WG_CREATE_CONFIG_FILE);

            return true;
        } catch (Exception $exception) {
            return false;
        }
    }

    public function delete(): bool
    {
        try {
            $this->runFile(self::WG_DELETE_CONFIG_FILE);

            return true;
        } catch (Exception $exception) {
            return false;
        }
    }

    private function runFile($file): void
    {
        $process = new Process([
            $this->server->ssh_command,
            "{$this->server->app_path}/$file",
            $this->name
        ]);
        $process->run();
    }
}
