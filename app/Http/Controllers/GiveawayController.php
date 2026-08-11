<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Giveaway\StoreGiveawayRequest;
use App\Http\Requests\Giveaway\UpdateGiveawayRequest;
use App\Http\Resources\GiveawayResource;
use App\Models\Giveaway;
use App\Services\Giveaways\GiveawayCrudService;
use App\Services\Giveaways\GiveawayDrawService;
use Illuminate\Http\Request;

class GiveawayController extends Controller
{
    public function __construct(
        private readonly GiveawayCrudService $giveaways,
        private readonly GiveawayDrawService $draws,
    ) {}

    public function index(Request $request)
    {
        $giveaways = Giveaway::query()
            ->with(['series', 'prizes', 'participants', 'winners'])
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get();

        return $this->inertia('Giveaways/Index', [
            'giveaways' => GiveawayResource::collection($giveaways)->toArray($request),
        ]);
    }

    public function create()
    {
        return $this->inertia('Giveaways/Form', [
            'mode' => 'create',
            'submit_url' => route('giveaways.store'),
            'giveaway' => [
                'title' => 'Розыгрыш Oksana VPN',
                'description' => '',
                'admins_only' => false,
                'starts_at' => now()->addHour()->toAtomString(),
                'ends_at' => now()->addDays(7)->toAtomString(),
                'series' => [
                    'auto_repeat_enabled' => false,
                    'repeat_delay_minutes' => 60,
                    'repeat_limit' => 5,
                ],
                'prizes' => [
                    ['duration_months' => 1, 'quantity' => 3, 'title' => 'Подписка на 1 месяц'],
                    ['duration_months' => 3, 'quantity' => 2, 'title' => 'Подписка на 3 месяца'],
                    ['duration_months' => 6, 'quantity' => 1, 'title' => 'Подписка на 6 месяцев'],
                ],
            ],
            'allowed_durations' => [1, 3, 6, 12],
        ]);
    }

    public function store(StoreGiveawayRequest $request)
    {
        $giveaway = $this->giveaways->create($request->toDto());

        return redirect()->route('giveaways.edit', $giveaway)
            ->with('success', 'Розыгрыш создан.');
    }

    public function edit(Request $request, Giveaway $giveaway)
    {
        $giveaway->load(['series', 'prizes', 'participants.user', 'winners.user', 'winners.prize']);

        return $this->inertia('Giveaways/Form', [
            'mode' => 'edit',
            'submit_url' => route('giveaways.update', $giveaway),
            'giveaway' => GiveawayResource::make($giveaway)->toArray($request),
            'allowed_durations' => [1, 3, 6, 12],
        ]);
    }

    public function update(UpdateGiveawayRequest $request, Giveaway $giveaway)
    {
        $this->giveaways->update($giveaway, $request->toDto());

        return redirect()->route('giveaways.edit', $giveaway)
            ->with('success', 'Розыгрыш обновлён.');
    }

    public function activate(Giveaway $giveaway)
    {
        $this->giveaways->activate($giveaway);

        return redirect()->route('giveaways.edit', $giveaway)
            ->with('success', 'Розыгрыш активирован.');
    }

    public function draw(Giveaway $giveaway)
    {
        $this->draws->draw($giveaway);

        return redirect()->route('giveaways.edit', $giveaway)
            ->with('success', 'Победители определены.');
    }

    public function cancel(Giveaway $giveaway)
    {
        $this->giveaways->cancel($giveaway);

        return redirect()->route('giveaways.edit', $giveaway)
            ->with('success', 'Розыгрыш отменён.');
    }
}
