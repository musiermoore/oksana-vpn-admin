<?php

namespace App\Http\Controllers;

use App\Enums\SupportTicketStatus;
use App\Http\Requests\SupportTicket\ReplySupportTicketRequest;
use App\Http\Resources\SupportTicketResource;
use App\Models\User;
use App\Services\TelegramApp\TelegramMiniAppSupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function __construct(
        private readonly TelegramMiniAppSupportService $support,
    ) {}

    public function index(Request $request)
    {
        $status = SupportTicketStatus::tryFrom($request->string('status')->toString());

        return $this->inertia('SupportTickets/Index', [
            'filters' => [
                'status' => $status?->value,
            ],
            'tickets' => SupportTicketResource::collection($this->support->listForAdmin($status))->toArray($request),
        ]);
    }

    public function show(Request $request, int $ticketId)
    {
        $ticket = $this->support->findForAdmin($ticketId);

        abort_unless($ticket, 404);

        return $this->inertia('SupportTickets/Show', [
            'ticket' => (new SupportTicketResource($ticket))->toArray($request),
            'statuses' => collect(SupportTicketStatus::cases())
                ->map(fn (SupportTicketStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function reply(ReplySupportTicketRequest $request, int $ticketId): RedirectResponse
    {
        $ticket = $this->support->findForAdmin($ticketId);

        abort_unless($ticket, 404);

        /** @var User $admin */
        $admin = $request->user();
        $this->support->addAdminReply($admin, $ticket, $request->toDto());

        return redirect()
            ->route('support-tickets.show', $ticketId)
            ->with('success', 'Ответ отправлен.');
    }
}
