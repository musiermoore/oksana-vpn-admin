<?php

declare(strict_types=1);

namespace App\Http\Controllers\TelegramApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\TelegramApp\TelegramAppGiveawayResource;
use App\Http\Resources\TelegramApp\TelegramAppUserResource;
use App\Services\Giveaways\GiveawayReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiveawayController extends Controller
{
    public function __construct(
        private readonly GiveawayReadService $giveaways,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $giveaway = $this->giveaways->visible();
        $user = $request->attributes->get('telegramAppUser');

        if (! $giveaway || ! $user) {
            return response()->json([
                'giveaway' => null,
                'participant' => null,
            ]);
        }

        $giveaway->loadMissing(['prizes', 'winners.user', 'winners.prize']);

        return response()->json([
            'giveaway' => TelegramAppGiveawayResource::make($giveaway)->toArray($request),
            'participant' => $this->giveaways->participantState($giveaway, $user),
            'user' => TelegramAppUserResource::make($user)->toArray($request),
        ]);
    }

    public function participate(Request $request): JsonResponse
    {
        $giveaway = $this->giveaways->visible();
        $user = $request->attributes->get('telegramAppUser');

        if (! $giveaway || ! $user) {
            return response()->json([
                'message' => 'Сейчас нет активного розыгрыша.',
            ], 422);
        }

        return response()->json([
            'participant' => $this->giveaways->participate($giveaway, $user),
        ]);
    }
}
