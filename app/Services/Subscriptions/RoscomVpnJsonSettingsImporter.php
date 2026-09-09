<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Enums\XrayRoutingOutbound;
use App\Models\XrayJsonSetting;
use App\Models\XrayRouting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RoscomVpnJsonSettingsImporter
{
    private const SOURCE = 'roscomvpn_json';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function import(array $payload): void
    {
        DB::transaction(function () use ($payload): void {
            XrayJsonSetting::query()
                ->where('source', self::SOURCE)
                ->update(['is_active' => false]);

            XrayRouting::query()
                ->where('source', self::SOURCE)
                ->update(['is_active' => false]);

            XrayJsonSetting::query()->create([
                'name' => $this->stringValue($payload, 'Name', 'Imported Xray JSON settings'),
                'description' => 'Imported from RoscomVPN-style JSON settings.',
                'source' => self::SOURCE,
                'dns' => $this->buildDnsSettings($payload),
                'routing' => $this->buildRoutingSettings($payload),
                'geodata' => $this->buildGeodataSettings($payload),
                'raw' => $payload,
                'is_active' => true,
                'imported_at' => $this->resolveImportedAt($payload),
            ]);

            foreach ($this->buildRoutingRules($payload) as $rule) {
                XrayRouting::query()->updateOrCreate(
                    [
                        'source' => self::SOURCE,
                        'source_key' => $rule['source_key'],
                    ],
                    [
                        'name' => $rule['name'],
                        'description' => 'Imported from RoscomVPN-style JSON settings.',
                        'outbound' => $rule['outbound'],
                        'subscription_types' => [
                            XrayRouting::SUBSCRIPTION_CONNECT,
                            XrayRouting::SUBSCRIPTION_CONNECT_WL,
                        ],
                        'rules' => $rule['rules'],
                        'sort_order' => $rule['sort_order'],
                        'is_active' => true,
                    ],
                );
            }
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildDnsSettings(array $payload): array
    {
        $directSites = $this->stringList($payload['DirectSites'] ?? []);
        $proxySites = $this->stringList($payload['ProxySites'] ?? []);
        $blockSites = $this->stringList($payload['BlockSites'] ?? []);

        $servers = [];
        $remoteAddress = $this->dnsAddress(
            $this->stringValue($payload, 'RemoteDNSType'),
            $this->stringValue($payload, 'RemoteDNSDomain'),
            $this->stringValue($payload, 'RemoteDNSIP', $this->stringValue($payload, 'RemoteDns'))
        );
        $domesticAddress = $this->dnsAddress(
            $this->stringValue($payload, 'DomesticDNSType'),
            $this->stringValue($payload, 'DomesticDNSDomain'),
            $this->stringValue($payload, 'DomesticDNSIP', $this->stringValue($payload, 'DomesticDns'))
        );

        if ($remoteAddress !== '' && [...$proxySites, ...$blockSites] !== []) {
            $servers[] = [
                'address' => $remoteAddress,
                'domains' => array_values([...$proxySites, ...$blockSites]),
                'skipFallback' => true,
            ];
        }

        if ($domesticAddress !== '' && $directSites !== []) {
            $servers[] = [
                'address' => $domesticAddress,
                'domains' => $directSites,
                'skipFallback' => true,
            ];
        }

        if ($remoteAddress !== '') {
            $servers[] = $remoteAddress;
        }

        return array_filter([
            'hosts' => $this->associativeStringMap($payload['DnsHosts'] ?? []),
            'servers' => $servers,
            'fakeDns' => $this->boolValue($payload, 'FakeDNS'),
        ], fn (mixed $value): bool => $value !== [] && $value !== null);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildRoutingSettings(array $payload): array
    {
        return array_filter([
            'domainStrategy' => $this->stringValue($payload, 'DomainStrategy'),
            'routeOrder' => $this->stringValue($payload, 'RouteOrder'),
            'globalProxy' => $this->boolValue($payload, 'GlobalProxy'),
        ], fn (mixed $value): bool => $value !== '' && $value !== null);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function buildGeodataSettings(array $payload): array
    {
        return array_filter([
            'geoip_url' => $this->stringValue($payload, 'Geoipurl'),
            'geosite_url' => $this->stringValue($payload, 'Geositeurl'),
        ], fn (string $value): bool => $value !== '');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{name:string, source_key:string, outbound:XrayRoutingOutbound, rules:array<string, mixed>, sort_order:int}>
     */
    private function buildRoutingRules(array $payload): array
    {
        $sortOrder = 0;
        $rules = [];

        foreach ($this->routeOrder($payload) as $outbound) {
            foreach ($this->rulesForOutbound($payload, $outbound) as $rule) {
                $rules[] = [
                    ...$rule,
                    'sort_order' => $sortOrder++,
                ];
            }
        }

        $defaultOutbound = $this->boolValue($payload, 'GlobalProxy') === true
            ? XrayRoutingOutbound::Proxy
            : XrayRoutingOutbound::Direct;

        $rules[] = [
            'name' => 'Default '.$defaultOutbound->value,
            'source_key' => 'default-'.$defaultOutbound->value,
            'outbound' => $defaultOutbound,
            'rules' => [
                'network' => 'tcp,udp',
            ],
            'sort_order' => $sortOrder,
        ];

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, XrayRoutingOutbound>
     */
    private function routeOrder(array $payload): array
    {
        $routeOrder = $this->stringValue($payload, 'RouteOrder', 'direct-proxy-block');
        $outbounds = [];

        foreach (explode('-', $routeOrder) as $outbound) {
            $outbound = match (trim(mb_strtolower($outbound))) {
                'direct' => XrayRoutingOutbound::Direct,
                'proxy' => XrayRoutingOutbound::Proxy,
                'block', 'blocked' => XrayRoutingOutbound::Blocked,
                default => null,
            };

            if ($outbound !== null && ! in_array($outbound, $outbounds, true)) {
                $outbounds[] = $outbound;
            }
        }

        foreach ([XrayRoutingOutbound::Direct, XrayRoutingOutbound::Proxy, XrayRoutingOutbound::Blocked] as $outbound) {
            if (! in_array($outbound, $outbounds, true)) {
                $outbounds[] = $outbound;
            }
        }

        return $outbounds;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{name:string, source_key:string, outbound:XrayRoutingOutbound, rules:array<string, mixed>}>
     */
    private function rulesForOutbound(array $payload, XrayRoutingOutbound $outbound): array
    {
        $prefix = match ($outbound) {
            XrayRoutingOutbound::Direct => 'Direct',
            XrayRoutingOutbound::Proxy => 'Proxy',
            XrayRoutingOutbound::Blocked => 'Block',
        };

        $rules = [];
        $domains = $this->stringList($payload[$prefix.'Sites'] ?? []);
        $ips = $this->stringList($payload[$prefix.'Ip'] ?? []);

        if ($domains !== []) {
            $rules[] = [
                'name' => $prefix.' domains',
                'source_key' => mb_strtolower($prefix).'-domains',
                'outbound' => $outbound,
                'rules' => [
                    'domain' => $domains,
                ],
            ];
        }

        if ($ips !== []) {
            $rules[] = [
                'name' => $prefix.' IP ranges',
                'source_key' => mb_strtolower($prefix).'-ips',
                'outbound' => $outbound,
                'rules' => [
                    'ip' => $ips,
                ],
            ];
        }

        return $rules;
    }

    private function dnsAddress(string $type, string $domain, string $ip): string
    {
        return mb_strtolower($type) === 'doh' && $domain !== ''
            ? $domain
            : $ip;
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->map(fn (string $item): string => trim($item))
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $value
     * @return array<string, string>
     */
    private function associativeStringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && is_string($item) && trim($key) !== '' && trim($item) !== '') {
                $map[trim($key)] = trim($item);
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stringValue(array $payload, string $key, string $default = ''): string
    {
        $value = $payload[$key] ?? $default;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function boolValue(array $payload, string $key): ?bool
    {
        $value = $payload[$key] ?? null;

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return match (mb_strtolower(trim($value))) {
                'true', '1', 'yes', 'on' => true,
                'false', '0', 'no', 'off' => false,
                default => null,
            };
        }

        if (is_int($value)) {
            return $value === 1 ? true : ($value === 0 ? false : null);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveImportedAt(array $payload): ?CarbonImmutable
    {
        $lastUpdated = $this->stringValue($payload, 'LastUpdated');

        if ($lastUpdated === '' || ! ctype_digit($lastUpdated)) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp((int) $lastUpdated);
    }
}
