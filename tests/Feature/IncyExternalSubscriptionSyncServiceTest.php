<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ExternalSubscriptionSourceFormat;
use App\Models\VlessExternalSubscription;
use App\Services\ExternalSubscriptions\VlessExternalSubscriptionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IncyExternalSubscriptionSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private const KEYMAT_URL = 'https://raw.githubusercontent.com/INCY-DEV/incy-link-encoder/main/src/keymat.ts';

    private const REMOTE_INCY_REDIRECT_URL = 'https://cdn.pecan.run/subs/339dccc0-7b60-4113-8dd2-b81ce1be2643/incy';

    private const INCY_LINK = 'incy://crypt1/cBPLdHf4fh1Whu-hrs_zaK5fh963i5REvIJBTbBuTU5z_qb_mJzAIhFUtdnd_ySLZ0O9GI-oi_uU67PFlGR3gT3OFWiAi0BjKnGQJYL3vTwSy13sLMf1PskI1WoHY0vpPjWsOSASFvfC2dc4UhxOj2neCRXiP25TdQKGNs_2so6wdxVed0E';

    private const DECODED_SUBSCRIPTION_URL = 'https://c.secu.lat/ssub/339dccc0-7b60-4113-8dd2-b81ce1be2643';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_sync_decodes_direct_incy_link_for_subscription_type(): void
    {
        Http::fake([
            self::KEYMAT_URL => Http::response($this->keymatSource()),
            self::DECODED_SUBSCRIPTION_URL => Http::response(base64_encode(
                "vless://uuid-1@de.example.com:443?type=tcp&security=reality#Germany 1\n"
            )),
        ]);

        $subscription = VlessExternalSubscription::query()->create([
            'name' => 'INCY WL',
            'type' => VlessExternalSubscription::TYPE_SUBSCRIPTION,
            'source_format' => ExternalSubscriptionSourceFormat::Incy->value,
            'source_url' => self::INCY_LINK,
            'filter_pattern' => 'germany',
            'is_active' => true,
            'is_ready' => true,
        ]);

        $result = app(VlessExternalSubscriptionSyncService::class)->sync($subscription);

        $this->assertCount(1, $result->configs);
        $this->assertSame('Germany 1', $result->configs[0]->name);
        $this->assertSame(
            'vless://uuid-1@de.example.com:443?type=tcp&security=reality#Germany 1',
            $result->configs[0]->url
        );
    }

    public function test_sync_resolves_https_page_that_redirects_to_incy_link(): void
    {
        Http::fake([
            self::KEYMAT_URL => Http::response($this->keymatSource()),
            self::REMOTE_INCY_REDIRECT_URL => Http::response(
                '<meta http-equiv="refresh" content="0;url=\''.self::INCY_LINK.'\'" />'
            ),
            self::DECODED_SUBSCRIPTION_URL => Http::response(base64_encode(
                "trojan://secret@de2.example.com:443?security=tls&type=tcp#Germany 2\n"
            )),
        ]);

        $subscription = VlessExternalSubscription::query()->create([
            'name' => 'INCY Redirect WL',
            'type' => VlessExternalSubscription::TYPE_SUBSCRIPTION,
            'source_format' => ExternalSubscriptionSourceFormat::Incy->value,
            'source_url' => self::REMOTE_INCY_REDIRECT_URL,
            'is_active' => true,
            'is_ready' => true,
        ]);

        $result = app(VlessExternalSubscriptionSyncService::class)->sync($subscription);

        $this->assertCount(1, $result->configs);
        $this->assertSame('Germany 2', $result->configs[0]->name);
        $this->assertSame('trojan', $result->configs[0]->protocol);
    }

    public function test_sync_falls_back_to_local_key_material_when_remote_source_is_unavailable(): void
    {
        Http::fake([
            self::KEYMAT_URL => Http::response('', 500),
            self::DECODED_SUBSCRIPTION_URL => Http::response(base64_encode(
                "vless://uuid-3@fallback.example.com:443?type=tcp&security=reality#Fallback\n"
            )),
        ]);

        $subscription = VlessExternalSubscription::query()->create([
            'name' => 'INCY fallback WL',
            'type' => VlessExternalSubscription::TYPE_SUBSCRIPTION,
            'source_format' => ExternalSubscriptionSourceFormat::Incy->value,
            'source_url' => self::INCY_LINK,
            'is_active' => true,
            'is_ready' => true,
        ]);

        $result = app(VlessExternalSubscriptionSyncService::class)->sync($subscription);

        $this->assertCount(1, $result->configs);
        $this->assertSame('Fallback', $result->configs[0]->name);
    }

    private function keymatSource(): string
    {
        $kmA = base64_decode((string) config('incy.keymat.local_km_a_b64'), true);
        $kmB = base64_decode((string) config('incy.keymat.local_km_b_b64'), true);

        $this->assertNotFalse($kmA);
        $this->assertNotFalse($kmB);

        $a = str_repeat("\0", 1024).$kmA;
        $b = str_repeat("\0", 2048).$kmB;

        return sprintf(
            "export const KEYMAT_A_B64 = '%s';\nexport const KEYMAT_B_B64 = '%s';\n",
            base64_encode($a),
            base64_encode($b),
        );
    }
}
