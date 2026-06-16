<?php

namespace App\Services;

use App\DTOs\Message\WelcomeMessagesData;
use App\Models\Message;
use App\Models\User;
use App\Repositories\MessageRepository;
use Illuminate\Support\Carbon;

class WelcomeMessageService
{
    public function __construct(
        private readonly MessageRepository $messages,
        private readonly TelegramBroadcastService $telegramBroadcastService,
    ) {}

    public function getWelcomeMessages(): array
    {
        $messages = $this->messages->getBySlugs([
            Message::SLUG_WELCOME_BASIC,
            Message::SLUG_WELCOME_EXTENDED,
        ]);

        return [
            'basic_text' => (string) optional($messages->get(Message::SLUG_WELCOME_BASIC))->text,
            'extended_text' => (string) optional($messages->get(Message::SLUG_WELCOME_EXTENDED))->text,
        ];
    }

    public function updateWelcomeMessages(WelcomeMessagesData $data): void
    {
        $this->messages->updateOrCreateBySlug(Message::SLUG_WELCOME_BASIC, [
            'name' => 'Welcome Basic',
            'text' => $data->basicText,
        ]);

        $this->messages->updateOrCreateBySlug(Message::SLUG_WELCOME_EXTENDED, [
            'name' => 'Welcome Extended',
            'text' => $data->extendedText,
        ]);
    }

    public function resolveWelcomeTextForRegistrationStatus(?User $user): string
    {
        $messages = $this->getWelcomeMessages();

        if ($user && $this->shouldShowExtendedMessage($user)) {
            $user->forceFill([
                'welcome_text_seen_at' => now(),
            ])->save();

            return $messages['extended_text'];
        }

        return $messages['basic_text'];
    }

    public function preview(?string $text): string
    {
        return $this->telegramBroadcastService->sanitizeMessage($text);
    }

    private function shouldShowExtendedMessage(User $user): bool
    {
        $seenAt = $user->welcome_text_seen_at;

        if (! $seenAt instanceof Carbon) {
            return true;
        }

        return $seenAt->lte(now()->subWeek());
    }
}
