<?php

namespace App\Services\Subscriptions;

use App\Services\Subscriptions\Builders\ClashBuilder;
use App\Services\Subscriptions\Builders\ConnectJsonBuilder;
use App\Services\Subscriptions\Builders\SingBoxBuilder;
use App\Services\Subscriptions\Builders\SubscriptionBuilder;
use App\Services\Subscriptions\Builders\UriBuilder;
use InvalidArgumentException;

class SubscriptionBuilderFactory
{
    public function __construct(
        private readonly UriBuilder $uriBuilder,
        private readonly ClashBuilder $clashBuilder,
        private readonly SingBoxBuilder $singBoxBuilder,
        private readonly ConnectJsonBuilder $connectJsonBuilder,
    ) {}

    public function make(string $format): SubscriptionBuilder
    {
        return match ($this->normalizeFormat($format)) {
            'uri' => $this->uriBuilder,
            'clash' => $this->clashBuilder,
            'sing-box' => $this->singBoxBuilder,
            'json' => $this->connectJsonBuilder,
            default => throw new InvalidArgumentException('Unsupported subscription format'),
        };
    }

    public function normalizeFormat(?string $format): string
    {
        return match (trim(mb_strtolower((string) $format))) {
            '', 'uri', 'links', 'raw' => 'uri',
            'clash', 'mihomo', 'meta', 'hiddify' => 'clash',
            'sing-box', 'singbox', 'sb' => 'sing-box',
            'json', 'connect-json' => 'json',
            default => throw new InvalidArgumentException('Unsupported subscription format'),
        };
    }
}
