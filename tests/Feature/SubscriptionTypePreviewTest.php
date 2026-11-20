<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConfigGroup;
use App\Models\SubscriptionType;
use App\Models\User;
use App\Models\VlessExternalSubscription;
use App\Models\XrayCustomConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SubscriptionTypePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_uses_draft_order_and_client_metadata_with_the_exact_json_output(): void
    {
        $first = $this->createSource('First', true);
        $second = $this->createSource('Second', false);
        $group = ConfigGroup::query()->create(['name' => 'Streaming']);
        $group->externalSubscriptions()->attach($first);

        $payload = $this->typePayload([
            $this->sourceItem($second, 1, true),
            $this->sourceItem($first, 0),
        ]);
        $this->actingAs($this->createUser(isAdmin: true));

        $preview = $this->postJson(route('subscription-types.preview-draft'), $payload)
            ->assertOk()
            ->assertJsonPath('profiles.0.name', 'First')
            ->assertJsonPath('profiles.0.category', 'Streaming')
            ->assertJsonPath('profiles.0.subtitle', 'About First')
            ->assertJsonPath('profiles.1.name', 'Second')
            ->assertJsonCount(2, 'profiles');

        $json = $preview->json('json');
        $this->assertSame($preview->json('content'), $json);
        $this->assertSame(['First', 'Second'], array_column(json_decode($json, true, flags: JSON_THROW_ON_ERROR), 'remarks'));

        $this->post(route('subscription-types.store'), $payload)->assertRedirect();
        $type = SubscriptionType::query()->where('slug', $payload['slug'])->firstOrFail();
        $this->assertTrue($type->items()->where('source_id', $second->id)->firstOrFail()->is_free);
        $this->assertFalse($second->refresh()->is_free);

        $this->get(route('subscription-types.show', $type->slug))->assertOk()->assertContent($json);

        $payload['items'][0]['is_free'] = false;
        $this->putJson(route('subscription-types.update', $type), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.source_id');

        unset($payload['items'][1]['is_free']);
        $payload['items'] = [$payload['items'][1]];
        $this->put(route('subscription-types.update', $type), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertFalse($type->items()->sole()->is_free);
    }

    public function test_uri_preview_keeps_the_profile_list_and_provides_xray_json(): void
    {
        $source = $this->createSource('URI example', true);
        $payload = $this->typePayload([$this->sourceItem($source, 0)]);
        $payload['format'] = 'uri';

        $preview = $this->actingAs($this->createUser(isAdmin: true))
            ->postJson(route('subscription-types.preview-draft'), $payload)
            ->assertOk()
            ->assertJsonPath('profiles.0.name', 'URI example')
            ->assertJsonPath('profiles.0.subtitle', 'About URI example');

        $this->assertSame('URI example', json_decode($preview->json('json'), true, flags: JSON_THROW_ON_ERROR)[0]['remarks']);
        $this->assertStringEndsWith('#URI%20example', $preview->json('readable_content'));
    }

    public function test_free_overrides_allow_inactive_users_to_receive_selected_external_and_public_custom_profiles(): void
    {
        $nativeFree = $this->createSource('Native free', true);
        $markedFree = $this->createSource('Marked free', false);
        $paid = $this->createSource('Paid', false);
        $public = $this->createCustomConfig($nativeFree, true);
        $private = $this->createCustomConfig($nativeFree, false);
        $category = ConfigGroup::query()->create(['name' => 'Custom profiles']);
        $category->xrayCustomConfigs()->attach($public);

        $payload = $this->typePayload([
            $this->sourceItem($paid, 0),
            $this->sourceItem($markedFree, 1, true),
            ['source_type' => 'xray_custom_config', 'source_id' => $public->id, 'sort_order' => 2, 'is_free' => true],
            ['source_type' => 'xray_custom_config', 'source_id' => $private->id, 'sort_order' => 3, 'is_free' => true],
        ]);
        $user = $this->createUser();
        $payload['requires_user_token'] = true;
        $payload['audience'] = 'user';
        $payload['user_id'] = $user->id;

        $this->actingAs($this->createUser(isAdmin: true))
            ->postJson(route('subscription-types.preview-draft'), $payload)
            ->assertOk()
            ->assertJsonPath('user.has_active_access', false)
            ->assertJsonPath('profiles.0.name', 'Marked free')
            ->assertJsonPath('profiles.1.category', 'Custom profiles')
            ->assertJsonPath('profiles.1.subtitle', 'Custom subtitle')
            ->assertJsonCount(2, 'profiles');

        $this->post(route('subscription-types.store'), $payload)->assertRedirect();
        $this->getJson(route('subscription-types.show', ['subscriptionType' => $payload['slug'], 'token' => $user->uuid]))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.remarks', 'Marked free');
        $this->assertFalse($public->refresh()->is_free);
    }

    private function createUser(bool $isAdmin = false): User
    {
        return User::query()->create([
            'name' => $isAdmin ? 'Admin' : 'Inactive user',
            'uuid' => fake()->uuid(),
            'is_admin' => $isAdmin,
        ]);
    }

    private function createSource(string $name, bool $isFree): VlessExternalSubscription
    {
        $uri = 'vless://'.fake()->uuid().'@external.example.com:443?type=tcp#External';
        $source = VlessExternalSubscription::query()->create([
            'name' => $name,
            'subtitle' => 'About '.$name,
            'connect_name_prefix' => $name,
            'type' => VlessExternalSubscription::TYPE_DIRECT,
            'source_url' => $uri,
            'is_free' => $isFree,
            'is_active' => true,
            'is_ready' => true,
        ]);
        $source->configs()->create([
            'config_key' => fake()->uuid(),
            'name' => $name,
            'normalized_name' => strtolower($name),
            'protocol' => 'vless',
            'url' => $uri,
            'sort_order' => 0,
        ]);

        return $source;
    }

    private function createCustomConfig(VlessExternalSubscription $source, bool $isPublic): XrayCustomConfig
    {
        return XrayCustomConfig::query()->create([
            'name' => 'Custom profile',
            'slug' => $isPublic ? 'public-profile' : 'private-profile',
            'subtitle' => 'Custom subtitle',
            'external_subscription_ids' => [$source->id],
            'is_active' => true,
            'is_public' => $isPublic,
            'is_free' => false,
        ]);
    }

    private function sourceItem(VlessExternalSubscription $source, int $order, bool $isFree = false): array
    {
        return ['source_type' => 'external_subscription', 'source_id' => $source->id, 'sort_order' => $order, 'is_free' => $isFree];
    }

    private function typePayload(array $items): array
    {
        return [
            'name' => 'Example subscription',
            'slug' => 'example-subscription',
            'kind' => 'connect',
            'format' => 'json',
            'is_active' => true,
            'requires_user_token' => false,
            'show_expired_placeholder' => false,
            'items' => $items,
        ];
    }
}
