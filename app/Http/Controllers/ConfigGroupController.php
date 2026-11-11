<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ConfigGroup;
use Illuminate\Http\Request;

class ConfigGroupController extends Controller
{
    public function index()
    {
        return $this->inertia('ConfigGroups/Index', [
            'groups' => ConfigGroup::query()->ordered()->get(['id', 'name', 'sort_order']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        ConfigGroup::query()->create([
            'name' => $data['name'],
            'sort_order' => ((int) ConfigGroup::query()->max('sort_order')) + 1,
        ]);

        return redirect()->back()->with('success', 'Группа конфигураций создана.');
    }

    public function update(Request $request, ConfigGroup $configGroup)
    {
        $configGroup->update($request->validate(['name' => ['required', 'string', 'max:255']]));

        return redirect()->back()->with('success', 'Группа конфигураций обновлена.');
    }

    public function destroy(ConfigGroup $configGroup)
    {
        $configGroup->delete();

        return redirect()->back()->with('success', 'Группа конфигураций удалена.');
    }
}
