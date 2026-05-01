<?php

namespace App\DTOs\Server;

readonly class ServerData
{
    public function __construct(
        public string $name,
        public string $code,
        public string $ip,
        public ?string $linkHost,
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
            'link_host' => $this->linkHost,
            'app_path' => $this->appPath,
            'ssh_private_key' => $this->sshPrivateKey,
            'ssh_public_key' => $this->sshPublicKey,
            'is_vless' => $this->isVless,
        ];
    }
}
