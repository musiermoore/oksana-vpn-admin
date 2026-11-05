<?php

declare(strict_types=1);

namespace App\DTOs\XrayGeodata;

use App\DTOs\Data;

class XrayGeodataData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $geoipUrl,
        public ?string $geositeUrl,
        public bool $isActive,
    ) {}

    /** @return array<string, mixed> */
    public function toModelAttributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'geoip_url' => $this->geoipUrl,
            'geosite_url' => $this->geositeUrl,
            'assets' => array_values(array_filter([
                $this->geoipUrl !== null ? ['url' => $this->geoipUrl, 'file' => 'geoip.dat'] : null,
                $this->geositeUrl !== null ? ['url' => $this->geositeUrl, 'file' => 'geosite.dat'] : null,
            ])),
            'is_active' => $this->isActive,
        ];
    }
}
