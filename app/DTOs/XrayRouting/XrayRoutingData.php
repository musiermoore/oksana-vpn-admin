<?php

declare(strict_types=1);

namespace App\DTOs\XrayRouting;

use App\Enums\XrayRoutingOutbound;
use Spatie\LaravelData\Data;

/**
 * @phpstan-type RulesPayload array<string, mixed>
 */
class XrayRoutingData extends Data
{
    /**
     * @param  array<int, string>  $subscription_types
     * @param  array<int, int>  $xray_inbound_ids
     * @param  array<int, int>  $external_subscription_config_ids
     * @param  array<string, mixed>  $rules
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public XrayRoutingOutbound $outbound,
        public array $subscription_types,
        public array $xray_inbound_ids,
        public array $external_subscription_config_ids,
        public array $rules,
        public int $sort_order,
        public bool $is_active,
    ) {}
}
