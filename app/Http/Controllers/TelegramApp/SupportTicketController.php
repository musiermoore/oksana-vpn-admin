<?php

namespace App\Http\Controllers\TelegramApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\TelegramApp\StoreSupportTicketMessageRequest;
use App\Http\Requests\TelegramApp\StoreSupportTicketRequest;
use App\Http\Resources\SupportTicketResource;
use App\Models\User;
use App\Models\Server;
use App\Services\TelegramApp\TelegramMiniAppSupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SupportTicketController extends Controller
{
    public function __construct(
        private readonly TelegramMiniAppSupportService $support,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'tickets' => SupportTicketResource::collection($this->support->listForUser($user))->resolve(),
        ]);
    }

    public function options(): JsonResponse
    {
        $servers = Server::query()
            ->where('is_active', true)
            ->with(['xrayInbounds' => fn ($query) => $query->where('is_active', true)->ordered()])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Server $server) => [
                'id' => $server->id,
                'name' => $server->name,
                'inbounds' => $server->xrayInbounds->map(fn ($inbound) => [
                    'id' => $inbound->id,
                    'name' => data_get($inbound->params, 'remark') ?: 'Подключение '.$inbound->external_id,
                ])->all(),
            ])->all();

        return response()->json(['servers' => $servers]);
    }

    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $ticket = $this->support->create($user, $request->toDto(), $request->file('attachments', []));

        return response()->json([
            'message' => 'Тикет создан.',
            'ticket' => (new SupportTicketResource($ticket))->resolve(),
        ], 201);
    }

    public function show(Request $request, int $ticketId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->support->findForUser($user, $ticketId);

        if (! $ticket) {
            return response()->json([
                'message' => 'Ticket not found.',
            ], 404);
        }

        return response()->json([
            'ticket' => (new SupportTicketResource($ticket))->resolve(),
        ]);
    }

    public function addMessage(StoreSupportTicketMessageRequest $request, int $ticketId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->support->findForUser($user, $ticketId);

        if (! $ticket) {
            return response()->json([
                'message' => 'Ticket not found.',
            ], 404);
        }

        $ticket = $this->support->addUserMessage($user, $ticket, $request->toDto(), $request->file('attachments', []));

        return response()->json([
            'message' => 'Сообщение отправлено.',
            'ticket' => (new SupportTicketResource($ticket))->resolve(),
        ]);
    }

    public function downloadAttachment(Request $request, int $ticketId, int $messageId, int $attachmentIndex)
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $this->support->findForUser($user, $ticketId);

        if (! $ticket) {
            abort(404);
        }

        $message = $ticket->messages->firstWhere('id', $messageId);
        $attachment = $message?->attachments[$attachmentIndex] ?? null;
        abort_unless(is_array($attachment) && Storage::disk('local')->exists($attachment['path'] ?? ''), 404);

        return Storage::disk('local')->download($attachment['path'], $attachment['name'] ?? 'attachment');
    }
}
