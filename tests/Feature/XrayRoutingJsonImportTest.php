<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\VlessConfig;
use App\Models\XrayJsonSetting;
use App\Models\XrayRouting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class XrayRoutingJsonImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_roscomvpn_json_settings_into_json_subscription(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
            'telegram_id' => '1',
            'is_admin' => true,
        ]);

        $settingsJson = json_encode([
            'Name' => 'RoscomVPN',
            'GlobalProxy' => 'true',
            'RemoteDns' => '8.8.8.8',
            'DomesticDns' => '77.88.8.8',
            'RemoteDNSType' => 'DoH',
            'RemoteDNSDomain' => 'https://8.8.8.8/dns-query',
            'RemoteDNSIP' => '8.8.8.8',
            'DomesticDNSType' => 'DoH',
            'DomesticDNSDomain' => 'https://77.88.8.8/dns-query',
            'DomesticDNSIP' => '77.88.8.8',
            'Geoipurl' => 'https://cdn.example.com/geoip.dat',
            'Geositeurl' => 'https://cdn.example.com/geosite.dat',
            'LastUpdated' => '1788941171',
            'DnsHosts' => [
                'lkfl2.nalog.ru' => '213.24.64.175',
            ],
            'RouteOrder' => 'block-proxy-direct',
            'DirectSites' => [
                'geosite:private',
                'geosite:category-ru',
            ],
            'DirectIp' => [
                'geoip:private',
            ],
            'ProxySites' => [
                'geosite:github',
            ],
            'ProxyIp' => [],
            'BlockSites' => [
                'geosite:torrent',
            ],
            'BlockIp' => [],
            'DomainStrategy' => 'IPIfNonMatch',
            'FakeDNS' => 'false',
        ], JSON_THROW_ON_ERROR);

        $this
            ->actingAs($admin)
            ->post(route('xray-routings.import'), [
                'settings_json' => $settingsJson,
            ])
            ->assertRedirect(route('xray-routings.index'));

        $this->assertSame(1, XrayJsonSetting::query()->active()->count());
        $this->assertSame(5, XrayRouting::query()->active()->count());

        $setting = XrayJsonSetting::query()->active()->firstOrFail();

        $this->assertSame('RoscomVPN', $setting->name);
        $this->assertSame('https://cdn.example.com/geoip.dat', data_get($setting->geodata, 'geoip_url'));
        $this->assertSame('https://cdn.example.com/geosite.dat', data_get($setting->geodata, 'geosite_url'));

        $user = $this->createActiveUser();
        $server = $this->createServer();

        VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => 10,
            'name' => 'imported-routing-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => '4f4419d7-d08e-4303-a13e-7a36f423a0f9',
            'port' => 443,
            'protocol' => 'vless',
            'type' => 'tcp',
            'encryption' => 'none',
            'security' => 'reality',
            'sni' => 'example.com',
            'pbk' => 'public-key',
            'sid' => 'abcd',
            'fp' => 'chrome',
            'spx' => '/',
        ]);

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('112238'),
            'i' => Crypt::encrypt((string) $user->id),
            'format' => 'json',
        ]));

        $response->assertOk();

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertSame('213.24.64.175', $payload[0]['dns']['hosts']['lkfl2.nalog.ru'] ?? null);
        $this->assertSame('https://8.8.8.8/dns-query', data_get($payload, '0.dns.servers.0.address'));
        $this->assertSame('https://77.88.8.8/dns-query', data_get($payload, '0.dns.servers.1.address'));
        $this->assertSame('IPIfNonMatch', data_get($payload, '0.routing.domainStrategy'));
        $this->assertSame('block', data_get($payload, '0.routing.rules.0.outboundTag'));
        $this->assertSame(['geosite:torrent'], data_get($payload, '0.routing.rules.0.domain'));
        $this->assertSame('proxy', data_get($payload, '0.routing.rules.1.outboundTag'));
        $this->assertSame(['geosite:github'], data_get($payload, '0.routing.rules.1.domain'));
        $this->assertSame('direct', data_get($payload, '0.routing.rules.2.outboundTag'));
        $this->assertSame(['geosite:private', 'geosite:category-ru'], data_get($payload, '0.routing.rules.2.domain'));
        $this->assertSame('direct', data_get($payload, '0.routing.rules.3.outboundTag'));
        $this->assertSame(['geoip:private'], data_get($payload, '0.routing.rules.3.ip'));
        $this->assertSame('proxy', data_get($payload, '0.routing.rules.4.outboundTag'));
        $this->assertSame('tcp,udp', data_get($payload, '0.routing.rules.4.network'));
    }

    private function createActiveUser(): User
    {
        $user = User::query()->create([
            'name' => 'Routing Import User',
            'telegram' => '@routing-import-user',
            'telegram_id' => '112238',
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => Carbon::now()->subDay()->toDateString(),
            'end_date' => Carbon::now()->addMonth()->toDateString(),
            'price' => 100,
        ]);

        return $user;
    }

    private function createServer(): Server
    {
        return Server::query()->create([
            'name' => 'Нидерланды',
            'code' => 'NL1',
            'ip' => '127.0.0.1',
            'link_host' => 'nl.oksana1984.ru',
            'is_https' => true,
            'type' => Server::TYPE_VLESS,
        ]);
    }
}
