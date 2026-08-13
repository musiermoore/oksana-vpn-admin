<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\VlessExternalSubscription\StoreVlessExternalSubscriptionRequest;
use App\Http\Requests\VlessExternalSubscription\UpdateVlessExternalSubscriptionRequest;
use App\Http\Resources\VlessExternalSubscriptionResource;
use App\Jobs\SyncVlessExternalSubscriptionJob;
use App\Enums\ExternalSubscriptionSourceFormat;
use App\Models\VlessExternalSubscription;
use App\Services\ExternalSubscriptions\VlessExternalSubscriptionSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use RuntimeException;

class VlessExternalSubscriptionController extends Controller
{
    public function __construct(
        private readonly VlessExternalSubscriptionSyncService $syncService,
    ) {}

    public function index(Request $request)
    {
        $subscriptions = VlessExternalSubscription::query()
            ->withCount('configs')
            ->ordered()
            ->get();

        return $this->inertia('VlessExternalSubscriptions/Index', [
            'subscriptions' => VlessExternalSubscriptionResource::collection($subscriptions)->toArray($request),
        ]);
    }

    public function create()
    {
        return $this->inertia('VlessExternalSubscriptions/Form', [
            'mode' => 'create',
            'method' => 'post',
            'submit_url' => route('vless-external-subscriptions.store'),
            'preview_url' => route('vless-external-subscriptions.preview'),
            'subscription' => null,
            'types' => $this->types(),
            'source_formats' => $this->sourceFormats(),
        ]);
    }

    public function store(StoreVlessExternalSubscriptionRequest $request): RedirectResponse
    {
        $subscription = VlessExternalSubscription::query()->create([
            ...$request->validated(),
            'sort_order' => (($maxSortOrder = VlessExternalSubscription::query()->max('sort_order')) !== null)
                ? ((int) $maxSortOrder + 1)
                : 0,
        ]);

        try {
            $this->syncService->sync($subscription);
        } catch (RuntimeException $exception) {
            $this->syncService->failSync($subscription, $exception->getMessage());
        }

        return redirect()->route('vless-external-subscriptions.edit', $subscription)
            ->with('success', 'Внешняя подписка создана.');
    }

    public function edit(Request $request, VlessExternalSubscription $vlessExternalSubscription)
    {
        $vlessExternalSubscription->load('configs');

        return $this->inertia('VlessExternalSubscriptions/Form', [
            'mode' => 'edit',
            'method' => 'patch',
            'submit_url' => route('vless-external-subscriptions.update', $vlessExternalSubscription),
            'preview_url' => route('vless-external-subscriptions.preview'),
            'subscription' => (new VlessExternalSubscriptionResource($vlessExternalSubscription))->toArray($request),
            'types' => $this->types(),
            'source_formats' => $this->sourceFormats(),
        ]);
    }

    public function update(
        UpdateVlessExternalSubscriptionRequest $request,
        VlessExternalSubscription $vlessExternalSubscription
    ): RedirectResponse {
        $vlessExternalSubscription->update($request->validated());

        try {
            $this->syncService->sync($vlessExternalSubscription);
        } catch (RuntimeException $exception) {
            $this->syncService->failSync($vlessExternalSubscription, $exception->getMessage());
        }

        return redirect()->back()->with('success', 'Внешняя подписка обновлена.');
    }

    public function destroy(VlessExternalSubscription $vlessExternalSubscription): RedirectResponse
    {
        $vlessExternalSubscription->delete();

        return redirect()->route('vless-external-subscriptions.index')
            ->with('success', 'Внешняя подписка удалена.');
    }

    public function preview(StoreVlessExternalSubscriptionRequest $request): JsonResponse
    {
        return response()->json($this->syncService->preview($request->validated()));
    }

    public function sync(VlessExternalSubscription $vlessExternalSubscription): RedirectResponse
    {
        Bus::dispatch(new SyncVlessExternalSubscriptionJob($vlessExternalSubscription->id));

        return redirect()->back()->with('success', 'Синхронизация поставлена в очередь.');
    }

    /**
     * @return array<int, array{value:string, label:string}>
     */
    private function types(): array
    {
        return [
            ['value' => VlessExternalSubscription::TYPE_SUBSCRIPTION, 'label' => 'Подписка'],
            ['value' => VlessExternalSubscription::TYPE_DIRECT, 'label' => 'Прямая ссылка'],
        ];
    }

    /**
     * @return array<int, array{value:string, label:string}>
     */
    private function sourceFormats(): array
    {
        return [
            ['value' => ExternalSubscriptionSourceFormat::Direct->value, 'label' => ExternalSubscriptionSourceFormat::Direct->label()],
            ['value' => ExternalSubscriptionSourceFormat::Incy->value, 'label' => ExternalSubscriptionSourceFormat::Incy->label()],
        ];
    }
}
