<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug', 64)->unique();
            $table->string('kind', 32);
            $table->string('format', 16)->default('uri');
            $table->boolean('is_active')->default(true);
            $table->boolean('show_expired_placeholder')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->index(['is_active', 'kind']);
        });

        Schema::create('subscription_type_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_type_id')
                ->constrained('subscription_types')
                ->cascadeOnDelete();
            $table->string('source_type', 64);
            $table->unsignedBigInteger('source_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['subscription_type_id', 'source_type', 'source_id'], 'subscription_type_item_unique');
            $table->index(['subscription_type_id', 'sort_order', 'id'], 'subscription_type_item_order');
        });

        $types = [
            ['name' => 'V1', 'slug' => 'v1', 'kind' => 'connect', 'format' => 'uri', 'is_active' => true, 'show_expired_placeholder' => true, 'is_system' => true],
            ['name' => 'V2', 'slug' => 'v2', 'kind' => 'connect_v2', 'format' => 'json', 'is_active' => true, 'show_expired_placeholder' => true, 'is_system' => true],
            ['name' => 'Free', 'slug' => 'free', 'kind' => 'connect_free', 'format' => 'uri', 'is_active' => true, 'show_expired_placeholder' => false, 'is_system' => true],
        ];

        foreach ($types as $type) {
            DB::table('subscription_types')->updateOrInsert(
                ['slug' => $type['slug']],
                [...$type, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $this->seedItems('v1', 'is_visible_in_connect', 'include_in_main_subscription', includeCustomConfigs: false);
        $this->seedItems('v2', 'is_visible_in_connect_v2', 'include_in_connect_v2', includeCustomConfigs: true);
        $this->seedFreeItems();
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_type_items');
        Schema::dropIfExists('subscription_types');
    }

    private function seedItems(string $slug, string $inboundVisibility, string $externalVisibility, bool $includeCustomConfigs): void
    {
        $typeId = (int) DB::table('subscription_types')->where('slug', $slug)->value('id');
        $items = [];

        DB::table('servers')
            ->whereNull('deleted_at')
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('xray_inbounds')
                ->whereColumn('xray_inbounds.server_id', 'servers.id')
                ->where("xray_inbounds.{$inboundVisibility}", true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'sort_order'])
            ->each(fn (object $row) => $items[] = [
                'source_type' => 'server', 'source_id' => (int) $row->id,
                'group_order' => (int) $row->sort_order, 'item_order' => 0,
            ]);

        $proxyVisibility = $slug === 'v2' ? 'is_visible_in_connect_v2' : 'is_visible_in_connect';

        DB::table('proxies')
            ->where($proxyVisibility, true)
            ->whereNotNull('server_id')
            ->join('servers', 'servers.id', '=', 'proxies.server_id')
            ->orderBy('servers.sort_order')
            ->orderBy('proxies.sort_order')
            ->orderBy('proxies.id')
            ->get(['proxies.id', 'servers.sort_order as group_order', 'proxies.sort_order as item_order'])
            ->each(fn (object $row) => $items[] = [
                'source_type' => 'proxy', 'source_id' => (int) $row->id,
                'group_order' => (int) $row->group_order, 'item_order' => (int) $row->item_order,
            ]);

        DB::table('vless_external_subscriptions')
            ->where($externalVisibility, true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'sort_order'])
            ->each(fn (object $row) => $items[] = [
                'source_type' => 'external_subscription', 'source_id' => (int) $row->id,
                'group_order' => (int) $row->sort_order, 'item_order' => 0,
            ]);

        if ($includeCustomConfigs) {
            DB::table('xray_custom_configs')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'sort_order'])
                ->each(fn (object $row) => $items[] = [
                    'source_type' => 'xray_custom_config', 'source_id' => (int) $row->id,
                    'group_order' => (int) $row->sort_order, 'item_order' => 0,
                ]);
        }

        $this->insertOrderedItems($typeId, $items);
    }

    private function seedFreeItems(): void
    {
        $typeId = (int) DB::table('subscription_types')->where('slug', 'free')->value('id');
        $items = DB::table('vless_external_subscriptions')
            ->where('is_active', true)
            ->where('is_free', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'sort_order'])
            ->map(fn (object $row): array => [
                'source_type' => 'external_subscription', 'source_id' => (int) $row->id,
                'group_order' => (int) $row->sort_order, 'item_order' => 0,
            ])
            ->all();

        $this->insertOrderedItems($typeId, $items);
    }

    /** @param array<int, array{source_type:string,source_id:int,group_order:int,item_order:int}> $items */
    private function insertOrderedItems(int $typeId, array $items): void
    {
        usort($items, fn (array $left, array $right): int => [
            $left['group_order'], $left['item_order'], $left['source_type'], $left['source_id'],
        ] <=> [
            $right['group_order'], $right['item_order'], $right['source_type'], $right['source_id'],
        ]);

        foreach ($items as $index => $item) {
            DB::table('subscription_type_items')->insert([
                'subscription_type_id' => $typeId,
                'source_type' => $item['source_type'],
                'source_id' => $item['source_id'],
                'sort_order' => $index,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
