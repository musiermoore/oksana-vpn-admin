<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Subscription\PreviewSubscriptionTypeRequest;
use App\Http\Requests\Subscription\SaveSubscriptionTypeRequest;
use App\Models\Proxy;
use App\Models\Server;
use App\Models\SubscriptionType;
use App\Models\VlessExternalSubscription;
use App\Models\XrayCustomConfig;
use App\Services\Crud\SubscriptionTypeCrudService;
use App\Services\Subscriptions\SubscriptionTypePreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use RuntimeException;

final class SubscriptionTypeController extends Controller
{
    public function index(SubscriptionTypeCrudService $types): InertiaResponse
    {
        return $this->inertia('SubscriptionTypes/Index', [
            'types' => $types->ordered()->map(fn (SubscriptionType $type): array => [
                'id' => (int) $type->id,
                'name' => (string) $type->name,
                'slug' => (string) $type->slug,
                'kind_label' => match ($type->kind) {
                    SubscriptionType::KIND_CONNECT_V2 => 'V2 — JSON',
                    SubscriptionType::KIND_CONNECT_FREE => 'Free',
                    default => 'V1 — Connect',
                },
                'format' => (string) $type->format,
                'is_active' => (bool) $type->is_active,
                'requires_user_token' => (bool) $type->requires_user_token,
                'is_system' => (bool) $type->is_system,
                'items_count' => $type->items->count(),
            ])->values()->all(),
        ]);
    }

    public function create(): InertiaResponse
    {
        return $this->inertia('SubscriptionTypes/Create', [
            'catalog' => $this->sourceCatalog(),
        ]);
    }

    public function edit(SubscriptionType $subscriptionType): InertiaResponse
    {
        return $this->inertia('SubscriptionTypes/Edit', [
            'type' => $this->typeData($subscriptionType->load('items')),
            'catalog' => $this->sourceCatalog(),
        ]);
    }

    public function store(SaveSubscriptionTypeRequest $request, SubscriptionTypeCrudService $types): RedirectResponse
    {
        $type = $types->create($request->toData());

        return redirect()->route('subscription-types.edit', $type)
            ->with('success', 'Тип подписки создан.');
    }

    public function update(
        SaveSubscriptionTypeRequest $request,
        SubscriptionType $subscriptionType,
        SubscriptionTypeCrudService $types,
    ): RedirectResponse {
        $types->update($subscriptionType, $request->toData());

        return redirect()->route('subscription-types.edit', $subscriptionType)
            ->with('success', 'Настройки подписки сохранены.');
    }

    public function destroy(SubscriptionType $subscriptionType, SubscriptionTypeCrudService $types): RedirectResponse
    {
        try {
            $types->delete($subscriptionType);
        } catch (RuntimeException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->route('subscription-types.index')->with('success', 'Тип подписки удалён.');
    }

    public function searchUsers(Request $request, SubscriptionTypePreviewService $previews): JsonResponse
    {
        $search = $request->query('search', '');

        return response()->json([
            'users' => $previews->searchUsers(is_string($search) ? mb_substr($search, 0, 100) : ''),
        ]);
    }

    public function preview(
        PreviewSubscriptionTypeRequest $request,
        SubscriptionType $subscriptionType,
        SubscriptionTypePreviewService $previews,
    ): JsonResponse {
        return response()->json($previews->preview($request->validated()));
    }

    public function previewDraft(
        PreviewSubscriptionTypeRequest $request,
        SubscriptionTypePreviewService $previews,
    ): JsonResponse {
        return response()->json($previews->preview($request->validated()));
    }

    /** @return array<string, mixed> */
    private function typeData(SubscriptionType $type): array
    {
        return [
            'id' => (int) $type->id,
            'name' => (string) $type->name,
            'slug' => (string) $type->slug,
            'kind' => (string) $type->kind,
            'format' => (string) $type->format,
            'is_active' => (bool) $type->is_active,
            'requires_user_token' => (bool) $type->requires_user_token,
            'show_expired_placeholder' => (bool) $type->show_expired_placeholder,
            'is_system' => (bool) $type->is_system,
            'items' => $type->items->map(fn ($item): array => [
                'source_type' => (string) $item->source_type,
                'source_id' => (int) $item->source_id,
                'sort_order' => (int) $item->sort_order,
            ])->values()->all(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function sourceCatalog(): array
    {
        $servers = Server::query()
            ->whereHas('xrayInbounds')
            ->ordered()
            ->get(['id', 'name', 'code']);
        $proxies = Proxy::query()
            ->whereNotNull('server_id')
            ->with('server:id,name')
            ->orderBy('server_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $externalSubscriptions = VlessExternalSubscription::query()
            ->ordered()
            ->get(['id', 'name', 'is_active', 'is_free']);
        $customConfigs = XrayCustomConfig::query()
            ->ordered()
            ->get(['id', 'name', 'is_active', 'is_public']);

        return [
            ...$servers->map(fn (Server $server): array => [
                'source_type' => 'server',
                'source_id' => (int) $server->id,
                'name' => (string) $server->name,
                'detail' => (string) $server->code,
                'group' => 'Серверы',
                'active' => (bool) $server->is_active,
                'is_free' => false,
                'edit_url' => route('servers.edit', $server),
            ])->all(),
            ...$proxies->map(fn (Proxy $proxy): array => [
                'source_type' => 'proxy',
                'source_id' => (int) $proxy->id,
                'name' => (string) $proxy->name,
                'detail' => (string) ($proxy->server?->name ?? 'Сервер не назначен'),
                'group' => 'Прокси',
                'active' => (bool) $proxy->is_ready,
                'is_free' => false,
                'edit_url' => route('proxies.edit', $proxy),
            ])->all(),
            ...$externalSubscriptions->map(fn (VlessExternalSubscription $subscription): array => [
                'source_type' => 'external_subscription',
                'source_id' => (int) $subscription->id,
                'name' => (string) $subscription->name,
                'detail' => $subscription->is_free ? 'Бесплатная' : 'Платная',
                'group' => 'Внешние подписки',
                'active' => (bool) $subscription->is_active,
                'is_free' => (bool) $subscription->is_free,
                'edit_url' => route('vless-external-subscriptions.edit', $subscription),
            ])->all(),
            ...$customConfigs->map(fn (XrayCustomConfig $config): array => [
                'source_type' => 'xray_custom_config',
                'source_id' => (int) $config->id,
                'name' => (string) $config->name,
                'detail' => $config->is_public ? 'Общая' : 'Назначается пользователям',
                'group' => 'Пользовательские конфигурации',
                'active' => (bool) $config->is_active,
                'is_free' => false,
                'edit_url' => route('xray-custom-configs.edit', $config),
            ])->all(),
        ];
    }
}
