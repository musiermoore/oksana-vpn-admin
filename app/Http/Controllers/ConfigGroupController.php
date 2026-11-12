<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ConfigGroup\StoreConfigGroupRequest;
use App\Http\Requests\ConfigGroup\UpdateConfigGroupRequest;
use App\Http\Resources\ConfigGroupResource;
use App\Models\ConfigGroup;
use App\Services\Crud\ConfigGroupCrudService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConfigGroupController extends Controller
{
    public function index(Request $request, ConfigGroupCrudService $service): \Inertia\Response
    {
        return $this->inertia('ConfigGroups/Index', [
            'groups' => ConfigGroupResource::collection($service->ordered())->toArray($request),
        ]);
    }

    public function store(StoreConfigGroupRequest $request, ConfigGroupCrudService $service): RedirectResponse
    {
        $service->create($request->toData());

        return redirect()->back()->with('success', 'Группа конфигураций создана.');
    }

    public function update(UpdateConfigGroupRequest $request, ConfigGroup $configGroup, ConfigGroupCrudService $service): RedirectResponse
    {
        $service->update($configGroup, $request->toData());

        return redirect()->back()->with('success', 'Группа конфигураций обновлена.');
    }

    public function destroy(ConfigGroup $configGroup, ConfigGroupCrudService $service): RedirectResponse
    {
        $service->delete($configGroup);

        return redirect()->back()->with('success', 'Группа конфигураций удалена.');
    }
}
