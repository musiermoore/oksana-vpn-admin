<?php

namespace App\Http\Controllers;

use App\Http\Requests\Message\UpdateWelcomeMessagesRequest;
use App\Services\WelcomeMessageService;
use Illuminate\Http\RedirectResponse;

class WelcomeMessageController extends Controller
{
    public function __construct(
        private readonly WelcomeMessageService $welcomeMessages,
    ) {}

    public function edit()
    {
        $messages = $this->welcomeMessages->getWelcomeMessages();

        return $this->inertia('Messages/EditWelcome', [
            'messages' => [
                ...$messages,
                'basic_preview' => $this->welcomeMessages->preview($messages['basic_text']),
                'extended_preview' => $this->welcomeMessages->preview($messages['extended_text']),
            ],
        ]);
    }

    public function update(UpdateWelcomeMessagesRequest $request): RedirectResponse
    {
        $this->welcomeMessages->updateWelcomeMessages($request->toDto());

        return back()->with('success', 'Приветственные сообщения сохранены.');
    }
}
