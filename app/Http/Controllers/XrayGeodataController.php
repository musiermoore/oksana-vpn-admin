<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\XrayGeodata\XrayGeodataData;
use App\Http\Requests\XrayGeodata\SaveXrayGeodataRequest;
use App\Models\XrayRoutingGeodata;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class XrayGeodataController extends Controller
{
    public function index()
    {
        $geodata = XrayRoutingGeodata::query()
            ->withCount(['customConfigs', 'externalSubscriptions', 'clientMappings'])
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (XrayRoutingGeodata $resource): array => $this->payload($resource))
            ->values();

        return $this->inertia('XrayGeodata/Index', [
            'create_url' => route('xray-geodata.create'),
            'geodata' => $geodata,
        ]);
    }

    public function create()
    {
        return $this->inertia('XrayGeodata/Form', [
            'mode' => 'create',
            'submit_url' => route('xray-geodata.store'),
            'resource' => null,
        ]);
    }

    public function store(SaveXrayGeodataRequest $request): JsonResponse|RedirectResponse
    {
        /** @var XrayGeodataData $data */
        $data = $request->toData();
        $resource = XrayRoutingGeodata::query()->create($data->toModelAttributes());

        if ($request->expectsJson()) {
            return response()->json(['resource' => ['id' => $resource->id, 'name' => $resource->name]]);
        }

        return redirect()->route('xray-geodata.edit', $resource)
            ->with('success', 'Источник геоданных создан.');
    }

    public function edit(XrayRoutingGeodata $xrayRoutingGeodata)
    {
        $xrayRoutingGeodata->loadCount(['customConfigs', 'externalSubscriptions', 'clientMappings']);

        return $this->inertia('XrayGeodata/Form', [
            'mode' => 'edit',
            'submit_url' => route('xray-geodata.update', $xrayRoutingGeodata),
            'resource' => $this->payload($xrayRoutingGeodata),
        ]);
    }

    public function update(
        SaveXrayGeodataRequest $request,
        XrayRoutingGeodata $xrayRoutingGeodata,
    ): RedirectResponse {
        /** @var XrayGeodataData $data */
        $data = $request->toData();
        $xrayRoutingGeodata->fill($data->toModelAttributes())->save();

        return redirect()->back()->with('success', 'Источник геоданных обновлён.');
    }

    public function destroy(XrayRoutingGeodata $xrayRoutingGeodata): RedirectResponse
    {
        if ($this->isInUse($xrayRoutingGeodata)) {
            return redirect()->back()->withErrors([
                'geodata' => 'Нельзя удалить источник геоданных, пока он выбран в конфигурациях или внешних подписках.',
            ]);
        }

        $xrayRoutingGeodata->delete();

        return redirect()->route('xray-geodata.index')->with('success', 'Источник геоданных удалён.');
    }

    private function isInUse(XrayRoutingGeodata $resource): bool
    {
        return $resource->customConfigs()->exists()
            || $resource->externalSubscriptions()->exists()
            || $resource->clientMappings()->exists();
    }

    /** @return array<string, mixed> */
    private function payload(XrayRoutingGeodata $resource): array
    {
        return [
            'id' => (int) $resource->id,
            'name' => $resource->name,
            'description' => $resource->description,
            'geoip_url' => $resource->geoip_url,
            'geosite_url' => $resource->geosite_url,
            'is_active' => (bool) $resource->is_active,
            'custom_configs_count' => (int) $resource->custom_configs_count,
            'external_subscriptions_count' => (int) $resource->external_subscriptions_count,
            'client_mappings_count' => (int) $resource->client_mappings_count,
            'links' => [
                'edit' => route('xray-geodata.edit', $resource),
                'destroy' => route('xray-geodata.destroy', $resource),
            ],
        ];
    }
}
