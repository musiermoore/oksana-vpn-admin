<?php

namespace App\DTOs\Server;

readonly class ServerData
{
    public function __construct(
        public string $name,
        public string $code,
        public string $ip,
        public bool $isHttps,
        public ?string $linkHost,
        public ?string $panelLink,
        public ?string $panelUsername,
        public ?string $panelPassword,
        public string $appPath,
        public ?string $sshPrivateKey,
        public ?string $sshPublicKey,
        public bool $isVless,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'ip' => $this->ip,
            'is_https' => $this->isHttps,
            'link_host' => $this->linkHost,
            'panel_link' => $this->panelLink,
            'panel_username' => $this->panelUsername,
            'panel_password' => $this->panelPassword,
            'app_path' => $this->appPath,
            'ssh_private_key' => $this->sshPrivateKey,
            'ssh_public_key' => $this->sshPublicKey,
            'is_vless' => $this->isVless,
        ];
    }
}
