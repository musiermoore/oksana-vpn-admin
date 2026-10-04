<?php

declare(strict_types=1);

namespace App\DTOs\XrayCustomConfig;

use Spatie\LaravelData\Data;

class XrayCustomConfigData extends Data
{
    /**
     * @param  array<int, int>  $xray_inbound_ids
     * @param  array<int, int>  $external_subscription_config_ids
     * @param  array<int, int>  $proxy_ids
     * @param  array<int, int>  $xray_routing_ids
     * @param  array<string, mixed>  $base_settings
     * @param  array<int, array<string, mixed>>  $outbound_groups
     * @param  array<int, array<string, mixed>>  $routes
     */
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description,
        public ?int $dns_settings_id,
        public ?int $geodata_id,
        public array $xray_inbound_ids,
        public array $external_subscription_config_ids,
        public array $proxy_ids,
        public array $xray_routing_ids,
        public array $base_settings,
        public array $outbound_groups,
        public array $routes,
        public bool $is_active,
    ) {}
}
