<?php

declare(strict_types=1);

namespace App\DTOs\Server;

use App\DTOs\Data;
use App\Models\Server;

class ServerData extends Data
{
    public function __construct(
        public string $name,
        public string $code,
        public string $ip,
        public string $type,
        public string $appPath,
        public bool $isHttps = false,
        public string $panelApiVersion = Server::PANEL_API_V2_9,
        public bool $isActive = true,
        public bool $isReady = false,
        public bool $hideConfigsForNonAdmins = false,
        public ?string $linkHost = null,
        public ?string $panelLink = null,
        public ?string $panelUsername = null,
        public ?string $panelPassword = null,
        public ?string $sshPrivateKey = null,
        public ?string $sshPublicKey = null,
        /** @var array<int, array{id:int, is_active:bool, is_public:bool}> */
        public array $inbounds = [],
    ) {}

    public function toServerAttributes(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'ip' => $this->ip,
            'type' => $this->type,
            'is_https' => $this->isHttps,
            'link_host' => $this->linkHost,
            'panel_link' => $this->panelLink,
            'panel_username' => $this->panelUsername,
            'panel_password' => $this->panelPassword,
            'panel_api_version' => $this->panelApiVersion,
            'app_path' => $this->appPath,
            'ssh_private_key' => $this->sshPrivateKey,
            'ssh_public_key' => $this->sshPublicKey,
            'is_active' => $this->isActive,
            'is_ready' => $this->isReady,
            'hide_configs_for_non_admins' => $this->hideConfigsForNonAdmins,
        ];
    }

    public function toArray(): array
    {
        return $this->toServerAttributes();
    }
}
