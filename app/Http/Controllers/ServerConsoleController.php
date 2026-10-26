<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\ServerConsole\ServerConsoleCommandData;
use App\DTOs\ServerConsole\ServerConsoleInputData;
use App\DTOs\ServerConsole\ServerConsoleResizeData;
use App\Http\Resources\ServerCommandTemplateResource;
use App\Http\Requests\ServerConsole\StoreServerConsoleCommandRequest;
use App\Http\Requests\ServerConsole\StoreServerConsoleInputRequest;
use App\Http\Requests\ServerConsole\StoreServerConsoleResizeRequest;
use App\Models\Server;
use App\Models\ServerCommandTemplate;
use App\Models\ServerConsoleSession;
use App\Services\ServerConsole\ServerConsoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use RuntimeException;

class ServerConsoleController extends Controller
{
    public function __construct(private readonly ServerConsoleService $console) {}

    public function show(Request $request, Server $server): Response
    {
        $this->authorizeConsole($request);
        $templates = ServerCommandTemplate::query()->orderBy('name')->get();

        return $this->inertia('Servers/Console', [
            'server' => [
                'id' => (int) $server->id,
                'name' => (string) $server->name,
                'ip' => (string) $server->ip,
            ],
            'templates' => ServerCommandTemplateResource::collection($templates)->toArray($request),
            'links' => [
                'start' => route('servers.console-sessions.store', $server),
                'commands' => route('servers.console-sessions.commands', '__SESSION__'),
                'input' => route('servers.console-sessions.input', '__SESSION__'),
                'resize' => route('servers.console-sessions.resize', '__SESSION__'),
                'close' => route('servers.console-sessions.close', '__SESSION__'),
                'servers' => route('servers.index'),
                'templates' => route('server-command-templates.index'),
            ],
        ]);
    }

    public function start(Request $request, Server $server): JsonResponse
    {
        $this->authorizeConsole($request);
        try {
            $session = $this->console->start($server, $request->user());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['uuid' => $session->uuid, 'status' => $session->status]);
    }

    public function command(StoreServerConsoleCommandRequest $request, string $sessionUuid): JsonResponse
    {
        $this->authorizeConsole($request);
        /** @var ServerConsoleCommandData $data */
        $data = $request->toData();
        $session = $this->ownedSession($request, $sessionUuid);

        try {
            if ($data->templateId !== null) {
                $this->console->runTemplate($session, ServerCommandTemplate::query()->findOrFail($data->templateId));
            } else {
                $this->console->command($session, (string) $data->command, 300);
            }
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['accepted' => true]);
    }

    public function input(StoreServerConsoleInputRequest $request, string $sessionUuid): JsonResponse
    {
        $this->authorizeConsole($request);
        /** @var ServerConsoleInputData $data */
        $data = $request->toData();
        $session = $this->ownedSession($request, $sessionUuid);

        try {
            $this->console->input($session, $data->input);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['accepted' => true]);
    }

    public function close(Request $request, string $sessionUuid): JsonResponse
    {
        $this->authorizeConsole($request);
        $this->console->close($this->ownedSession($request, $sessionUuid));

        return response()->json(['accepted' => true]);
    }

    public function resize(StoreServerConsoleResizeRequest $request, string $sessionUuid): JsonResponse
    {
        $this->authorizeConsole($request);
        /** @var ServerConsoleResizeData $data */
        $data = $request->toData();

        try {
            $this->console->resize($this->ownedSession($request, $sessionUuid), $data->columns, $data->rows);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['accepted' => true]);
    }

    private function ownedSession(Request $request, string $uuid): ServerConsoleSession
    {
        return ServerConsoleSession::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    private function authorizeConsole(Request $request): void
    {
        abort_unless((bool) $request->user()?->is_admin, 403);
    }

}
