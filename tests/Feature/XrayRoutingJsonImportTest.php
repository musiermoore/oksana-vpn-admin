<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\VlessConfig;
use App\Models\VlessExternalSubscription;
use App\Models\VlessExternalSubscriptionConfig;
use App\Models\XrayJsonSetting;
use App\Models\XrayInbound;
use App\Models\XrayRouting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class XrayRoutingJsonImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(storage_path('app/xray-geodata'));
    }

    public function test_create_page_eager_loads_xray_inbound_targets_without_params_payload(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
            'telegram_id' => '1',
            'is_admin' => true,
        ]);

        $server = Server::query()->create([
            'name' => 'Finland',
            'code' => 'FI-1',
            'sort_order' => 1,
            'ip' => '10.0.0.10',
            'type' => Server::TYPE_VLESS,
        ]);

        $inbound = XrayInbound::query()->create([
            'server_id' => $server->id,
            'external_id' => 101,
            'sort_order' => 1,
            'is_active' => true,
            'is_public' => false,
            'params' => [
                'heavy' => str_repeat('x', 4096),
            ],
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this
            ->actingAs($admin)
            ->get(route('xray-routings.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('XrayRoutings/Create')
                ->where('target_tree.servers.0.inbounds.0.id', $inbound->id)
                ->where('target_tree.servers.0.inbounds.0.external_id', 101)
                ->where('target_tree.servers.0.inbounds.0.is_active', true)
                ->where('target_tree.servers.0.inbounds.0.is_public', false)
            );

        $xrayInboundSelect = collect(DB::getQueryLog())
            ->pluck('query')
            ->first(function (string $query): bool {
                $normalizedQuery = str_replace(['`', '"'], '', $query);

                return str_contains($normalizedQuery, 'from xray_inbounds')
                    && str_contains($normalizedQuery, 'server_id in');
            });

        $this->assertIsString($xrayInboundSelect);
        $normalizedXrayInboundSelect = str_replace(['`', '"'], '', $xrayInboundSelect);
        $this->assertStringContainsString('select id, server_id, external_id, sort_order, is_active, is_public', $normalizedXrayInboundSelect);
        $this->assertStringNotContainsString('params', $normalizedXrayInboundSelect);
    }

    public function test_index_paginates_routing_rules(): void
    {
        $admin = $this->createAdmin();

        foreach (range(1, 16) as $order) {
            XrayRouting::query()->create([
                'name' => 'Routing rule '.$order,
                'description' => null,
                'source' => 'manual',
                'source_key' => null,
                'outbound' => 'proxy',
                'subscription_types' => [XrayRouting::SUBSCRIPTION_CONNECT],
                'xray_inbound_ids' => [],
                'external_subscription_config_ids' => [],
                'rules' => ['domain' => ['geosite:rule-'.$order]],
                'sort_order' => $order,
                'is_active' => true,
            ]);
        }

        $this
            ->actingAs($admin)
            ->get(route('xray-routings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('XrayRoutings/Index')
                ->where('create_page_url', route('xray-routings.create'))
                ->has('routings.data', 15)
                ->where('routings.total', 16)
                ->where('routings.per_page', 15)
                ->where('routings.data.0.name', 'Routing rule 1')
                ->where('routings.data.0.links.edit', route('xray-routings.edit', XrayRouting::query()->ordered()->first()))
            );
    }

    public function test_edit_page_loads_routing_rule_form_data(): void
    {
        $admin = $this->createAdmin();
        $routing = XrayRouting::query()->create([
            'name' => 'Editable routing',
            'description' => 'Manual rule',
            'source' => 'manual',
            'source_key' => null,
            'outbound' => 'direct',
            'subscription_types' => [XrayRouting::SUBSCRIPTION_CONNECT_WL],
            'xray_inbound_ids' => [10],
            'external_subscription_config_ids' => [],
            'rules' => ['domain' => ['geosite:editable']],
            'sort_order' => 7,
            'is_active' => false,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('xray-routings.edit', $routing))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('XrayRoutings/Edit')
                ->where('submit_url', route('xray-routings.update', $routing))
                ->where('index_url', route('xray-routings.index'))
                ->where('routing.name', 'Editable routing')
                ->where('routing.outbound', 'direct')
                ->where('routing.subscription_types.0', XrayRouting::SUBSCRIPTION_CONNECT_WL)
                ->where('routing.rules.domain.0', 'geosite:editable')
            );
    }

    public function test_admin_can_import_roscomvpn_json_settings_into_json_subscription(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
            'telegram_id' => '1',
            'is_admin' => true,
        ]);

        Http::fake([
            'https://cdn.example.com/geoip.dat' => Http::response('geoip-data-v1'),
            'https://cdn.example.com/geosite.dat' => Http::response('geosite-data-v1'),
        ]);

        $settingsJson = json_encode($this->routingPayload(), JSON_THROW_ON_ERROR);

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
        $this->assertTrue(data_get($setting->geodata, 'use_chunk_files'));
        $this->assertSame('1788941171', data_get($setting->geodata, 'last_updated'));
        $this->assertFileExists(storage_path('app/xray-geodata/geoip.dat'));
        $this->assertFileExists(storage_path('app/xray-geodata/geosite.dat'));

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
        $this->assertSame('0 4 * * *', data_get($payload, '0.geodata.cron'));
        $this->assertSame('proxy', data_get($payload, '0.geodata.outbound'));
        $this->assertSame('https://cdn.example.com/geoip.dat', data_get($payload, '0.geodata.assets.0.url'));
        $this->assertSame('geoip.dat', data_get($payload, '0.geodata.assets.0.file'));
        $this->assertSame('https://cdn.example.com/geosite.dat', data_get($payload, '0.geodata.assets.1.url'));
        $this->assertSame('geosite.dat', data_get($payload, '0.geodata.assets.1.file'));
        $this->assertSame('IPIfNonMatch', data_get($payload, '0.routing.domainStrategy'));
        $this->assertSame([], data_get($payload, '0.routing.rules'));
        $this->assertStringContainsString('"settings": {}', (string) $response->getContent());
    }

    public function test_import_without_geodata_urls_keeps_default_asset_behavior(): void
    {
        Http::fake();

        $payload = $this->routingPayload([
            'Geoipurl' => '',
            'Geositeurl' => '',
            'LastUpdated' => '',
        ]);

        $this
            ->actingAs($this->createAdmin())
            ->post(route('xray-routings.import'), [
                'settings_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('xray-routings.index'));

        Http::assertNothingSent();

        $user = $this->createActiveUser();
        $this->createConfig($user);

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('112238'),
            'i' => Crypt::encrypt((string) $user->id),
            'format' => 'json',
        ]));

        $response->assertOk();

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertArrayNotHasKey('geodata', $payload[0]);
        $this->assertSame([], data_get($payload, '0.routing.rules'));
    }

    public function test_import_uses_cached_geodata_for_same_urls_and_last_updated(): void
    {
        Http::fake([
            'https://cdn.example.com/geoip.dat' => Http::response('geoip-data-v1'),
            'https://cdn.example.com/geosite.dat' => Http::response('geosite-data-v1'),
        ]);

        $payload = $this->routingPayload();
        $admin = $this->createAdmin();

        $this->actingAs($admin)->post(route('xray-routings.import'), [
            'settings_json' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
        $this->actingAs($admin)->post(route('xray-routings.import'), [
            'settings_json' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        Http::assertSentCount(2);
        $this->assertSame('geoip-data-v1', file_get_contents(storage_path('app/xray-geodata/geoip.dat')));
        $this->assertSame('geosite-data-v1', file_get_contents(storage_path('app/xray-geodata/geosite.dat')));
    }

    public function test_import_downloads_geodata_again_when_last_updated_changes(): void
    {
        Http::fake([
            'https://cdn.example.com/geoip.dat' => Http::sequence()
                ->push('geoip-data-v1')
                ->push('geoip-data-v2'),
            'https://cdn.example.com/geosite.dat' => Http::sequence()
                ->push('geosite-data-v1')
                ->push('geosite-data-v2'),
        ]);

        $admin = $this->createAdmin();

        $this->actingAs($admin)->post(route('xray-routings.import'), [
            'settings_json' => json_encode($this->routingPayload(), JSON_THROW_ON_ERROR),
        ]);
        $this->actingAs($admin)->post(route('xray-routings.import'), [
            'settings_json' => json_encode($this->routingPayload(['LastUpdated' => '1788941172']), JSON_THROW_ON_ERROR),
        ]);

        Http::assertSentCount(4);
        $this->assertSame('geoip-data-v2', file_get_contents(storage_path('app/xray-geodata/geoip.dat')));
        $this->assertSame('geosite-data-v2', file_get_contents(storage_path('app/xray-geodata/geosite.dat')));
    }

    public function test_import_returns_validation_error_when_geodata_download_fails(): void
    {
        Http::fake([
            'https://cdn.example.com/geoip.dat' => Http::response('', 500),
        ]);

        $this
            ->actingAs($this->createAdmin())
            ->from(route('xray-routings.index'))
            ->post(route('xray-routings.import'), [
                'settings_json' => json_encode($this->routingPayload(), JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('xray-routings.index'))
            ->assertSessionHasErrors('settings_json');

        $this->assertSame(0, XrayJsonSetting::query()->active()->count());
        $this->assertSame(0, XrayRouting::query()->active()->count());
        $this->assertFileDoesNotExist(storage_path('app/xray-geodata/geoip.dat'));
    }

    public function test_admin_can_update_routing_rule_from_interface(): void
    {
        Http::fake();

        $admin = $this->createAdmin();

        $this->actingAs($admin)->post(route('xray-routings.import'), [
            'settings_json' => json_encode($this->routingPayload([
                'Geoipurl' => '',
                'Geositeurl' => '',
            ]), JSON_THROW_ON_ERROR),
        ]);

        $routing = XrayRouting::query()
            ->where('source_key', 'proxy-domains')
            ->firstOrFail();

        $this
            ->actingAs($admin)
            ->put(route('xray-routings.update', $routing), [
                'name' => 'Edited proxy domains',
                'description' => 'Manual override',
                'outbound' => 'direct',
                'subscription_types' => [XrayRouting::SUBSCRIPTION_CONNECT],
                'xray_inbound_ids' => [],
                'external_subscription_config_ids' => [],
                'rules_json' => json_encode(['domain' => ['geosite:edited']], JSON_THROW_ON_ERROR),
                'sort_order' => 99,
                'is_active' => false,
            ])
            ->assertRedirect(route('xray-routings.index'));

        $routing->refresh();

        $this->assertSame('Edited proxy domains', $routing->name);
        $this->assertSame('Manual override', $routing->description);
        $this->assertSame('direct', $routing->outbound->value);
        $this->assertSame([XrayRouting::SUBSCRIPTION_CONNECT], $routing->subscription_types);
        $this->assertSame([], $routing->xray_inbound_ids);
        $this->assertSame([], $routing->external_subscription_config_ids);
        $this->assertSame(['domain' => ['geosite:edited']], $routing->rules);
        $this->assertSame(99, $routing->sort_order);
        $this->assertFalse($routing->is_active);
    }

    public function test_update_drops_deleted_and_inactive_targets_before_validation(): void
    {
        $admin = $this->createAdmin();
        $activeServer = Server::query()->create([
            'name' => 'Active server',
            'code' => 'ACTIVE',
            'ip' => '10.0.0.20',
            'type' => Server::TYPE_VLESS,
            'is_active' => true,
        ]);
        $inactiveInbound = XrayInbound::query()->create([
            'server_id' => $activeServer->id,
            'external_id' => 202,
            'is_active' => false,
        ]);
        $routing = XrayRouting::query()->create([
            'name' => 'Stale targets',
            'outbound' => 'direct',
            'subscription_types' => [XrayRouting::SUBSCRIPTION_CONNECT],
            'rules' => ['domain' => ['domain:example.test']],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('xray-routings.update', $routing), [
                'name' => 'Cleaned targets',
                'description' => null,
                'outbound' => 'direct',
                'subscription_types' => [XrayRouting::SUBSCRIPTION_CONNECT],
                'xray_inbound_ids' => [$inactiveInbound->id, 999999],
                'external_subscription_config_ids' => [999999],
                'proxy_ids' => [999999],
                'rules_json' => json_encode(['domain' => ['domain:clean.test']], JSON_THROW_ON_ERROR),
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->assertRedirect(route('xray-routings.index'));

        $routing->refresh();

        $this->assertSame([], $routing->xray_inbound_ids);
        $this->assertSame([], $routing->external_subscription_config_ids);
        $this->assertSame([], $routing->proxy_ids);
        $this->assertSame(['domain' => ['domain:clean.test']], $routing->rules);
    }

    public function test_updated_subscription_types_control_generated_json_routing(): void
    {
        Http::fake();

        $admin = $this->createAdmin();

        $this->actingAs($admin)->post(route('xray-routings.import'), [
            'settings_json' => json_encode($this->routingPayload([
                'Geoipurl' => '',
                'Geositeurl' => '',
            ]), JSON_THROW_ON_ERROR),
        ]);

        $routing = XrayRouting::query()
            ->where('source_key', 'proxy-domains')
            ->firstOrFail();
        $externalConfig = $this->createWhitelistExternalConfig();

        $this->actingAs($admin)->put(route('xray-routings.update', $routing), [
            'name' => $routing->name,
            'description' => $routing->description,
            'outbound' => 'proxy',
            'subscription_types' => [XrayRouting::SUBSCRIPTION_CONNECT_WL],
            'xray_inbound_ids' => [],
            'external_subscription_config_ids' => [$externalConfig->id],
            'rules_json' => json_encode(['domain' => ['geosite:wl-only']], JSON_THROW_ON_ERROR),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $user = $this->createActiveUser();
        $this->createConfig($user);

        $standardResponse = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('112238'),
            'i' => Crypt::encrypt((string) $user->id),
            'format' => 'json',
        ]));

        $whiteListResponse = $this->get(route('vless.connect-wl', [
            'tg' => Crypt::encrypt('112238'),
            'i' => Crypt::encrypt((string) $user->id),
        ]));

        $standardResponse->assertOk();
        $whiteListResponse->assertOk();

        $this->assertStringNotContainsString('geosite:wl-only', (string) $standardResponse->getContent());
        $this->assertStringContainsString('geosite:wl-only', (string) $whiteListResponse->getContent());
    }

    public function test_updated_xray_inbound_targets_control_generated_json_routing(): void
    {
        Http::fake();

        $admin = $this->createAdmin();

        $this->actingAs($admin)->post(route('xray-routings.import'), [
            'settings_json' => json_encode($this->routingPayload([
                'Geoipurl' => '',
                'Geositeurl' => '',
            ]), JSON_THROW_ON_ERROR),
        ]);

        $user = $this->createActiveUser();
        $firstConfig = $this->createConfig($user, 10);
        $secondConfig = $this->createConfig($user, 11);
        $firstInbound = XrayInbound::query()->findOrFail($firstConfig->xray_inbound_id);
        $secondInbound = XrayInbound::query()->findOrFail($secondConfig->xray_inbound_id);
        $routing = XrayRouting::query()
            ->where('source_key', 'proxy-domains')
            ->firstOrFail();

        $this->actingAs($admin)->put(route('xray-routings.update', $routing), [
            'name' => $routing->name,
            'description' => $routing->description,
            'outbound' => 'proxy',
            'subscription_types' => [XrayRouting::SUBSCRIPTION_CONNECT],
            'xray_inbound_ids' => [$firstInbound->id],
            'external_subscription_config_ids' => [],
            'rules_json' => json_encode(['domain' => ['geosite:first-inbound-only']], JSON_THROW_ON_ERROR),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('112238'),
            'i' => Crypt::encrypt((string) $user->id),
            'format' => 'json',
        ]));

        $response->assertOk();

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertCount(2, $payload);
        $ruleSets = collect($payload)
            ->map(fn (array $profile): array => data_get($profile, 'routing.rules', []))
            ->all();

        $this->assertContains([[
            'type' => 'field',
            'domain' => ['geosite:first-inbound-only'],
            'outboundTag' => 'proxy',
        ]], $ruleSets);
        $this->assertContains([], $ruleSets);
        $this->assertNotSame($firstInbound->id, $secondInbound->id);
    }

    public function test_admin_can_create_multiple_manual_routing_rules_for_different_targets(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createActiveUser();
        $firstConfig = $this->createConfig($user, 10);
        $secondConfig = $this->createConfig($user, 11);

        $this->actingAs($admin)->post(route('xray-routings.store'), [
            'name' => 'First inbound routing',
            'description' => null,
            'outbound' => 'proxy',
            'subscription_types' => [XrayRouting::SUBSCRIPTION_CONNECT],
            'xray_inbound_ids' => [$firstConfig->xray_inbound_id],
            'external_subscription_config_ids' => [],
            'rules_json' => json_encode(['domain' => ['geosite:first']], JSON_THROW_ON_ERROR),
            'sort_order' => 1,
            'is_active' => true,
        ])->assertRedirect(route('xray-routings.index'));

        $this->actingAs($admin)->post(route('xray-routings.store'), [
            'name' => 'Second inbound routing',
            'description' => null,
            'outbound' => 'direct',
            'subscription_types' => [XrayRouting::SUBSCRIPTION_CONNECT],
            'xray_inbound_ids' => [$secondConfig->xray_inbound_id],
            'external_subscription_config_ids' => [],
            'rules_json' => json_encode(['domain' => ['geosite:second']], JSON_THROW_ON_ERROR),
            'sort_order' => 2,
            'is_active' => true,
        ])->assertRedirect(route('xray-routings.index'));

        $response = $this->get(route('vless.connect', [
            'tg' => Crypt::encrypt('112238'),
            'i' => Crypt::encrypt((string) $user->id),
            'format' => 'json',
        ]));

        $response->assertOk();

        $payload = json_decode((string) $response->getContent(), true);

        $ruleSets = collect($payload)
            ->map(fn (array $profile): array => data_get($profile, 'routing.rules.0', []))
            ->all();

        $this->assertContains(['type' => 'field', 'domain' => ['geosite:first'], 'outboundTag' => 'proxy'], $ruleSets);
        $this->assertContains(['type' => 'field', 'domain' => ['geosite:second'], 'outboundTag' => 'direct'], $ruleSets);
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

    private function createAdmin(): User
    {
        return User::query()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
            'telegram_id' => '1',
            'is_admin' => true,
        ]);
    }

    private function createConfig(User $user, int $inboundId = 10): VlessConfig
    {
        $server = $this->createServer();

        return VlessConfig::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'inbound_id' => $inboundId,
            'name' => 'imported-routing-config',
            'is_active' => true,
            'enable' => true,
            'uuid' => '4f4419d7-d08e-4303-a13e-'.str_pad((string) $inboundId, 12, '0', STR_PAD_LEFT),
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
    }

    private function createWhitelistExternalConfig(): VlessExternalSubscriptionConfig
    {
        $subscription = VlessExternalSubscription::query()->create([
            'name' => 'White List',
            'type' => VlessExternalSubscription::TYPE_SUBSCRIPTION,
            'source_url' => 'https://example.com/sub',
            'filter_pattern' => 'германия',
            'is_active' => true,
            'is_ready' => true,
            'include_in_whitelist' => true,
        ]);

        return VlessExternalSubscriptionConfig::query()->create([
            'vless_external_subscription_id' => $subscription->id,
            'config_key' => 'wl-1',
            'name' => 'Германия #1',
            'normalized_name' => 'германия #1',
            'protocol' => 'vless',
            'url' => 'vless://4f4419d7-d08e-4303-a13e-7a36f423a0f9@de.example.com:443?type=tcp&security=reality&sni=example.com&pbk=public-key&sid=abcd&fp=chrome#de-1',
            'sort_order' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function routingPayload(array $overrides = []): array
    {
        return [
            'Name' => 'RoscomVPN',
            'GlobalProxy' => 'true',
            'UseChunkFiles' => 'true',
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
            ...$overrides,
        ];
    }
}
