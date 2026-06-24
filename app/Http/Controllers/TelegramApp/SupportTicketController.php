<?php

namespace App\Http\Controllers\TelegramApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\TelegramApp\StoreSupportTicketMessageRequest;
use App\Http\Requests\TelegramApp\StoreSupportTicketRequest;
use App\Http\Resources\SupportTicketResource;
use App\Models\User;
use App\Services\TelegramApp\TelegramMiniAppSupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $ticket = $this->support->create($user, $request->toDto());

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

        $ticket = $this->support->addUserMessage($user, $ticket, $request->toDto());

        return response()->json([
            'message' => 'Сообщение отправлено.',
            'ticket' => (new SupportTicketResource($ticket))->resolve(),
        ]);
    }
}
