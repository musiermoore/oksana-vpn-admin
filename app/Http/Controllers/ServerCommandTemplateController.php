<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ServerCommandTemplate\StoreServerCommandTemplateRequest;
use App\Http\Requests\ServerCommandTemplate\UpdateServerCommandTemplateRequest;
use App\Http\Resources\ServerCommandTemplateResource;
use App\Models\ServerCommandTemplate;
use App\Services\Crud\ServerCommandTemplateCrudService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ServerCommandTemplateController extends Controller
{
    public function __construct(private readonly ServerCommandTemplateCrudService $templates) {}

    public function index(Request $request): Response
    {
        $templates = ServerCommandTemplate::query()->orderBy('name')->get();

        return $this->inertia('ServerCommandTemplates/Index', [
            'templates' => ServerCommandTemplateResource::collection($templates)->toArray($request),
            'create_url' => route('server-command-templates.create'),
        ]);
    }

    public function create(): Response
    {
        return $this->inertia('ServerCommandTemplates/Form', [
            'template' => null,
            'submit_url' => route('server-command-templates.store'),
            'method' => 'post',
            'formats' => $this->formats(),
        ]);
    }

    public function store(StoreServerCommandTemplateRequest $request): RedirectResponse
    {
        $this->templates->create($request->toData());

        return redirect()->route('server-command-templates.index')->with('success', 'Шаблон команды создан.');
    }

    public function edit(Request $request, ServerCommandTemplate $serverCommandTemplate): Response
    {
        return $this->inertia('ServerCommandTemplates/Form', [
            'template' => ServerCommandTemplateResource::make($serverCommandTemplate)->toArray($request),
            'submit_url' => route('server-command-templates.update', $serverCommandTemplate),
            'method' => 'put',
            'formats' => $this->formats(),
        ]);
    }

    public function update(
        UpdateServerCommandTemplateRequest $request,
        ServerCommandTemplate $serverCommandTemplate,
    ): RedirectResponse
    {
        $this->templates->update($serverCommandTemplate, $request->toData());

        return redirect()->route('server-command-templates.index')->with('success', 'Шаблон команды обновлён.');
    }

    public function destroy(ServerCommandTemplate $serverCommandTemplate): RedirectResponse
    {
        $this->templates->delete($serverCommandTemplate);

        return redirect()->route('server-command-templates.index')->with('success', 'Шаблон команды удалён.');
    }

    /** @return array<int, array{value:string,label:string}> */
    private function formats(): array
    {
        return [
            ['value' => ServerCommandTemplate::FORMAT_SCRIPT, 'label' => 'Сценарий командной оболочки'],
            ['value' => ServerCommandTemplate::FORMAT_BLOCKS, 'label' => 'Блоки команд (JSON)'],
        ];
    }
}
