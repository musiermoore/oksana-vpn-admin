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
        'rules',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'outbound' => XrayRoutingOutbound::class,
            'subscription_types' => 'array',
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
