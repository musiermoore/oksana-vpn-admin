<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\XrayRoutingOutbound;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class XrayRouting extends Model
{
    public const SUBSCRIPTION_CONNECT = 'connect';

    public const SUBSCRIPTION_CONNECT_WL = 'connect_wl';

    protected $fillable = [
        'name',
        'description',
        'source',
        'source_key',
        'outbound',
        'subscription_types',
        'xray_inbound_ids',
        'external_subscription_config_ids',
        'rules',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'outbound' => XrayRoutingOutbound::class,
            'subscription_types' => 'array',
            'xray_inbound_ids' => 'array',
            'external_subscription_config_ids' => 'array',
            'rules' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function appliesTo(string $subscriptionType): bool
    {
        $subscriptionTypes = $this->subscription_types;

        if (! is_array($subscriptionTypes) || $subscriptionTypes === []) {
            return true;
        }

        return in_array($subscriptionType, $subscriptionTypes, true);
    }

    public function appliesToInbound(?int $xrayInboundId): bool
    {
        $xrayInboundIds = $this->xray_inbound_ids;

        if (! is_array($xrayInboundIds) || $xrayInboundIds === [] || $xrayInboundId === null) {
            return false;
        }

        return in_array($xrayInboundId, array_map('intval', $xrayInboundIds), true);
    }

    public function appliesToExternalSubscriptionConfig(?int $externalSubscriptionConfigId): bool
    {
        $externalSubscriptionConfigIds = $this->external_subscription_config_ids;

        if (! is_array($externalSubscriptionConfigIds)
            || $externalSubscriptionConfigIds === []
            || $externalSubscriptionConfigId === null) {
            return false;
        }

        return in_array($externalSubscriptionConfigId, array_map('intval', $externalSubscriptionConfigIds), true);
    }

    public function appliesToAnyTarget(?int $xrayInboundId, ?int $externalSubscriptionConfigId): bool
    {
        return $this->appliesToInbound($xrayInboundId)
            || $this->appliesToExternalSubscriptionConfig($externalSubscriptionConfigId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toXrayRule(string $directTag, string $proxyTag, string $blockTag): array
    {
        $rules = is_array($this->rules) ? $this->rules : [];

        return [
            'type' => 'field',
            ...$rules,
            'outboundTag' => $this->resolveOutboundTag($directTag, $proxyTag, $blockTag),
        ];
    }

    private function resolveOutboundTag(string $directTag, string $proxyTag, string $blockTag): string
    {
        $outbound = $this->outbound instanceof XrayRoutingOutbound
            ? $this->outbound
            : XrayRoutingOutbound::from((string) $this->outbound);

        return match ($outbound) {
            XrayRoutingOutbound::Direct => $directTag,
            XrayRoutingOutbound::Proxy => $proxyTag,
            XrayRoutingOutbound::Blocked => $blockTag,
        };
    }
}
