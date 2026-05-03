<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notification\StoreNotificationRequest;
use App\Models\User;
use App\Services\TelegramBroadcastService;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    public function __construct(
        private readonly TelegramBroadcastService $broadcastService,
    ) {}

    public function create()
    {
        $users = User::query()
            ->select(['id', 'name', 'telegram', 'telegram_id', 'deleted_at'])
            ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->get();

        return $this->inertia('Notifications/Create', [
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'telegram' => $user->telegram,
                'telegram_id' => $user->telegram_id,
                'is_active' => $user->is_active,
                'has_telegram_chat' => ! empty($user->telegram_id),
            ])->values(),
        ]);
    }

    public function store(StoreNotificationRequest $request): RedirectResponse
    {
        $userIds = $request->validated('user_ids', []);
        $targets = User::query()
            ->select(['id', 'name', 'telegram', 'telegram_id', 'deleted_at'])
            ->when(
                ! $request->boolean('send_to_all'),
                fn ($query) => $query->whereIn('id', $userIds),
            )
            ->orderBy('id')
            ->get();

        if ($targets->isEmpty()) {
            return back()
                ->withInput()
                ->with('error', 'Получатели не найдены.');
        }

        $messageHtml = $this->broadcastService->sanitizeMessage(
            (string) $request->validated('message_html', '')
        );

        $result = $this->broadcastService->send(
            $targets,
            $messageHtml,
            $request->file('image')
        );

        $redirect = back()->with(
            'success',
            "Отправка завершена: {$result['sent']} из {$result['total']}."
        );

        if ($result['skipped'] > 0 || $result['failed'] > 0) {
            $details = [];

            if ($result['skipped'] > 0) {
                $details[] = "Без Telegram ID: {$result['skipped']}";
            }

            if ($result['failed'] > 0) {
                $details[] = "Ошибки отправки: {$result['failed']}";
            }

            if ($result['failed_users'] !== []) {
                $details[] = 'Проблемные пользователи: '.implode(', ', $result['failed_users']);
            }

            $redirect->with('error', implode('. ', $details).'.');
        }

        return $redirect;
    }
}
