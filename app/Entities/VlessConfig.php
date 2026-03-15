<?php

namespace App\Entities;

class VlessConfig
{
    public function __construct(
        private ?int $serverId = null,
        private ?int $userId = null,

        private ?string $name = null,
        private ?string $description = null,
        private bool $isActive = true,

        private ?string $uuid = null,

        private ?int $port = null,

        private ?string $type = null,
        private ?string $encryption = null,
        private ?string $security = null,

        private ?string $pbk = null,
        private ?string $fp = null,
        private ?string $sni = null,
        private ?string $sid = null,
        private ?string $spx = null,
    )
    {
    }

    public static function fromData(array $data): self
    {
        $config = new self();

        $config->setServerId($data['server_id'] ?? null);
        $config->setUserId($data['user_id'] ?? null);

        $config->setName($data['name'] ?? null);
        $config->setDescription($data['description'] ?? null);
        $config->setIsActive($data['is_active'] ?? true);

        $config->setUuid($data['uuid'] ?? null);

        $config->setPort($data['port'] ?? null);

        $config->setType($data['type'] ?? null);
        $config->setEncryption($data['encryption'] ?? null);
        $config->setSecurity($data['security'] ?? null);

        $config->setPbk($data['pbk'] ?? null);
        $config->setFp($data['fp'] ?? null);
        $config->setSni($data['sni'] ?? null);
        $config->setSid($data['sid'] ?? null);
        $config->setSpx($data['spx'] ?? null);

        return $config;
    }

    public function toArray(): array
    {
        return [
            'server_id' => $this->serverId,
            'user_id' => $this->userId,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'uuid' => $this->uuid,
            'port' => $this->port,
            'type' => $this->type,
            'encryption' => $this->encryption,
            'security' => $this->security,
            'pbk' => $this->pbk,
            'fp' => $this->fp,
            'sni' => $this->sni,
            'sid' => $this->sid,
            'spx' => $this->spx,
        ];
    }

    public function getServerId(): ?int
    {
        return $this->serverId;
    }

    public function setServerId(?int $serverId): self
    {
        $this->serverId = $serverId;
        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(?string $uuid): self
    {
        $this->uuid = $uuid;
        return $this;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function setPort(?int $port): self
    {
        $this->port = $port;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getEncryption(): ?string
    {
        return $this->encryption;
    }

    public function setEncryption(?string $encryption): self
    {
        $this->encryption = $encryption;
        return $this;
    }

    public function getSecurity(): ?string
    {
        return $this->security;
    }

    public function setSecurity(?string $security): self
    {
        $this->security = $security;
        return $this;
    }

    public function getPbk(): ?string
    {
        return $this->pbk;
    }

    public function setPbk(?string $pbk): self
    {
        $this->pbk = $pbk;
        return $this;
    }

    public function getFp(): ?string
    {
        return $this->fp;
    }

    public function setFp(?string $fp): self
    {
        $this->fp = $fp;
        return $this;
    }

    public function getSni(): ?string
    {
        return $this->sni;
    }

    public function setSni(?string $sni): self
    {
        $this->sni = $sni;
        return $this;
    }

    public function getSid(): ?string
    {
        return $this->sid;
    }

    public function setSid(?string $sid): self
    {
        $this->sid = $sid;
        return $this;
    }

    public function getSpx(): ?string
    {
        return $this->spx;
    }

    public function setSpx(?string $spx): self
    {
        $this->spx = $spx;
        return $this;
    }
}
