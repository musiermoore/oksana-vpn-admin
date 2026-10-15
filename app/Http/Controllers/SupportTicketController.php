<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SupportTicketStatus;
use App\Http\Requests\SupportTicket\ReplySupportTicketRequest;
use App\Http\Resources\SupportTicketResource;
use App\Models\User;
use App\Models\Config;
use App\Models\VlessConfig;
use App\Services\TelegramApp\TelegramMiniAppSupportService;
use App\Services\VlessDeepLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SupportTicketController extends Controller
{
    public function __construct(
        private readonly TelegramMiniAppSupportService $support,
        private readonly VlessDeepLinkService $vlessDeepLinks,
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
            'supportUser' => $this->supportUserDetails($ticket->user),
        ]);
    }

    private function supportUserDetails(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'subscription_expires_at' => $user->latestSubscription?->end_date,
            'transactions' => $user->transactions()->with('type')->latest()->limit(5)->get()->map(fn ($transaction) => [
                'id' => $transaction->id,
                'date' => $transaction->created_at?->toAtomString(),
                'amount' => $transaction->amount,
                'approved' => $transaction->is_approved,
                'type' => $transaction->type?->name ?? '—',
                'description' => $transaction->description,
            ])->all(),
            'connect_links' => [
                ['label' => 'Подключение', 'url' => $this->vlessDeepLinks->getConnectUrl($user)],
                ['label' => 'Подключение v2', 'url' => $this->vlessDeepLinks->getConnectV2Url($user)],
                ['label' => 'Подключение WL', 'url' => $this->vlessDeepLinks->getConnectUrlForRoute($user, 'vless.connect-wl')],
            ],
            'amnezia_configs' => [
                ...Config::query()
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->whereHas('server', fn ($query) => $query->where('is_active', true))
                    ->with('server')
                    ->get()
                    ->map(fn (Config $config) => [
                    'label' => trim(($config->server?->name ? $config->server->name.' — ' : '').$config->name),
                    'url' => route('configs.edit', $config),
                ])->all(),
                ...VlessConfig::query()
                    ->where('user_id', $user->id)
                    ->whereRaw('LOWER(protocol) = ?', ['amneziawg'])
                    ->where('is_active', true)
                    ->where('enable', true)
                    ->whereHas('server', fn ($query) => $query->where('is_active', true))
                    ->whereHas('xrayInbound', fn ($query) => $query->where('is_active', true)->where('is_public', true))
                    ->with(['server', 'xrayInbound'])
                    ->get()
                    ->map(fn (VlessConfig $config) => [
                    'label' => trim(($config->server?->name ? $config->server->name.' — ' : '').$config->name),
                    'url' => route('xray-configs.edit', ['protocol' => $config->protocol, 'config' => $config->id]),
                ])->all(),
            ],
            'connected_devices' => $user->connectedDevices()->latest('last_connection_at')->limit(5)->get()->map(fn ($device) => [
                'label' => $device->label,
                'device' => $device->device,
                'user_agent' => $device->user_agent,
                'ip_address' => $device->ip_address,
                'last_connection_at' => $device->last_connection_at?->toAtomString(),
                'connection_route' => $device->connection_route,
                'connection_count' => $device->connection_count,
            ])->all(),
        ];
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

    public function close(int $ticketId): RedirectResponse
    {
        $ticket = $this->support->findForAdmin($ticketId);

        abort_unless($ticket, 404);

        $this->support->close($ticket);

        return redirect()
            ->route('support-tickets.show', $ticketId)
            ->with('success', 'Обращение закрыто.');
    }

    public function downloadAttachment(int $ticketId, int $messageId, int $attachmentIndex)
    {
        $ticket = $this->support->findForAdmin($ticketId);
        abort_unless($ticket, 404);

        $message = $ticket->messages->firstWhere('id', $messageId);
        $attachment = $message?->attachments[$attachmentIndex] ?? null;
        abort_unless(is_array($attachment) && Storage::disk('local')->exists($attachment['path'] ?? ''), 404);

        return Storage::disk('local')->download($attachment['path'], $attachment['name'] ?? 'attachment');
    }
}
