<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\XrayRouting\XrayRoutingImportData;
use App\Enums\XrayRoutingOutbound;
use App\Http\Requests\XrayRouting\ImportXrayRoutingSettingsRequest;
use App\Models\XrayJsonSetting;
use App\Models\XrayRouting;
use App\Services\Subscriptions\RoscomVpnJsonSettingsImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class XrayRoutingController extends Controller
{
    public function index(Request $request)
    {
        $activeSettings = XrayJsonSetting::query()
            ->latestActive()
            ->first();

        $routings = XrayRouting::query()
            ->active()
            ->ordered()
            ->get();

        return $this->inertia('XrayRoutings/Index', [
            'import_url' => route('xray-routings.import'),
            'active_settings' => $activeSettings ? [
                'id' => $activeSettings->id,
                'name' => $activeSettings->name,
                'source' => $activeSettings->source,
                'dns' => $activeSettings->dns,
                'routing' => $activeSettings->routing,
                'geodata' => $activeSettings->geodata,
                'imported_at' => $activeSettings->imported_at?->toDateTimeString(),
                'created_at' => $activeSettings->created_at?->toDateTimeString(),
            ] : null,
            'routings' => $routings->map(function (XrayRouting $routing): array {
                $outbound = $routing->outbound;

                return [
                    'id' => $routing->id,
                    'name' => $routing->name,
                    'outbound' => $outbound instanceof XrayRoutingOutbound ? $outbound->value : (string) $outbound,
                    'subscription_types' => $routing->subscription_types,
                    'rules' => $routing->rules,
                    'sort_order' => $routing->sort_order,
                ];
            })->values(),
        ]);
    }

    public function import(
        ImportXrayRoutingSettingsRequest $request,
        RoscomVpnJsonSettingsImporter $importer
    ): RedirectResponse {
        /** @var XrayRoutingImportData $data */
        $data = $request->toDto();

        $importer->import($data->payload);

        return redirect()
            ->route('xray-routings.index')
            ->with('success', 'Xray routing settings imported.');
    }
}
