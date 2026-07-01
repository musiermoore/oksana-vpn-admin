<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\XuiConfigServiceFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Throwable;

class XuiDebugController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->inertia('XuiDebug/Index', [
            'servers' => $this->getServerOptions(),
            'presets' => $this->getPresets(),
            'initial_form' => $this->buildFormState($request),
            'result' => session('xui_debug_result'),
        ]);
    }

    public function execute(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'server_id' => ['required', 'integer', 'exists:servers,id'],
            'preset' => ['required', 'string'],
            'method' => ['required', 'string', 'in:GET,POST,PUT,PATCH,DELETE'],
            'endpoint' => ['required', 'string', 'max:1000'],
            'encoding' => ['required', 'string', 'in:form,json'],
            'payload' => ['nullable', 'string'],
        ]);

        $server = Server::query()->findOrFail($validated['server_id']);
        $payload = $this->decodePayload((string) ($validated['payload'] ?? ''), $validated['encoding']);

        try {
            $service = XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server);
            $result = $service->sendDiagnosticRequest(
                method: $validated['method'],
                path: $validated['endpoint'],
                payload: $payload,
                encoding: $validated['encoding'],
            );

            return redirect()
                ->route('xui-debug.index', $request->except(['payload']))
                ->withInput($request->all())
                ->with('success', '3x-ui запрос выполнен.')
                ->with('xui_debug_result', [
                    'server' => [
                        'id' => $server->id,
                        'name' => $server->name,
                        'panel_link' => $server->panel_link,
                        'panel_api_version' => $server->getPanelApiVersion(),
                    ],
                    ...$result,
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('xui-debug.index', $request->except(['payload']))
                ->withInput($request->all())
                ->with('error', $exception->getMessage())
                ->with('xui_debug_result', [
                    'server' => [
                        'id' => $server->id,
                        'name' => $server->name,
                        'panel_link' => $server->panel_link,
                        'panel_api_version' => $server->getPanelApiVersion(),
                    ],
                    'ok' => false,
                    'status' => null,
                    'headers' => [],
                    'body' => null,
                    'json' => null,
                    'request' => [
                        'method' => $validated['method'],
                        'path' => $validated['endpoint'],
                        'encoding' => $validated['encoding'],
                        'payload' => $payload,
                    ],
                    'exception' => [
                        'class' => $exception::class,
                        'message' => $exception->getMessage(),
                    ],
                ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getServerOptions(): array
    {
        return Server::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Server $server) => [
                'id' => $server->id,
                'name' => $server->name,
                'code' => $server->code,
                'type' => $server->type,
                'panel_link' => $server->panel_link,
                'panel_api_version' => $server->getPanelApiVersion(),
                'label' => sprintf(
                    '#%d %s (%s) · %s · %s',
                    $server->id,
                    $server->name,
                    $server->code,
                    $server->type,
                    $server->getPanelApiVersion(),
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function getPresets(): array
    {
        return [
            [
                'value' => 'online-clients',
                'label' => 'Online clients',
                'method' => 'POST',
                'endpoint' => '/panel/api/clients/onlines',
                'encoding' => 'form',
                'payload' => '{}',
            ],
            [
                'value' => 'client-ips',
                'label' => 'Client IPs by email',
                'method' => 'POST',
                'endpoint' => '/panel/api/clients/ips/your_email_here',
                'encoding' => 'form',
                'payload' => '{}',
            ],
            [
                'value' => 'client-list',
                'label' => 'Clients list',
                'method' => 'GET',
                'endpoint' => '/panel/api/clients/list',
                'encoding' => 'json',
                'payload' => '{}',
            ],
            [
                'value' => 'client-traffic',
                'label' => 'Client traffic by email',
                'method' => 'GET',
                'endpoint' => '/panel/api/clients/traffic/your_email_here',
                'encoding' => 'json',
                'payload' => '{}',
            ],
            [
                'value' => 'inbounds-list',
                'label' => 'Inbounds list',
                'method' => 'GET',
                'endpoint' => '/panel/api/inbounds/list',
                'encoding' => 'json',
                'payload' => '{}',
            ],
            [
                'value' => 'other',
                'label' => 'Other endpoint',
                'method' => 'GET',
                'endpoint' => '/panel/api/',
                'encoding' => 'json',
                'payload' => '{}',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFormState(Request $request): array
    {
        $presets = collect($this->getPresets())->keyBy('value');
        $selectedPreset = (string) $request->old('preset', $request->query('preset', 'online-clients'));
        $preset = $presets->get($selectedPreset, $presets->get('online-clients'));
        $defaultServerId = Server::query()->orderBy('name')->value('id');

        return [
            'server_id' => (int) $request->old('server_id', $request->query('server_id', $defaultServerId ?? 0)),
            'preset' => $selectedPreset,
            'method' => (string) $request->old('method', $request->query('method', $preset['method'] ?? 'GET')),
            'endpoint' => (string) $request->old('endpoint', $request->query('endpoint', $preset['endpoint'] ?? '/panel/api/')),
            'encoding' => (string) $request->old('encoding', $request->query('encoding', $preset['encoding'] ?? 'json')),
            'payload' => (string) $request->old('payload', $preset['payload'] ?? '{}'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $payload, string $encoding): array
    {
        $payload = trim($payload);

        if ($payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            abort(422, 'Payload must be valid JSON.');
        }

        if ($decoded === null) {
            return [];
        }

        if (! is_array($decoded)) {
            abort(422, 'Payload JSON must decode to an object or array.');
        }

        if ($encoding === 'form' && array_is_list($decoded)) {
            abort(422, 'Form payload must be a JSON object.');
        }

        return $decoded;
    }
}
