<?php

declare(strict_types=1);

namespace App\Http\Resources\TelegramApp;

use App\Models\Giveaway;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TelegramAppGiveawayResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Giveaway $giveaway */
        $giveaway = $this->resource;

        return [
            'id' => $giveaway->id,
            'title' => $giveaway->title,
            'description' => $giveaway->description,
            'status' => $giveaway->status,
            'status_label' => $giveaway->formattedStatus(),
            'starts_at' => optional($giveaway->starts_at)?->toAtomString(),
            'ends_at' => optional($giveaway->ends_at)?->toAtomString(),
            'prizes' => $giveaway->prizes->map(fn ($prize) => [
                'duration_months' => (int) $prize->duration_months,
                'quantity' => (int) $prize->quantity,
                'title' => $prize->resolvedTitle(),
            ])->values()->all(),
            'winners' => $giveaway->winners->map(fn ($winner) => [
                'name' => $winner->user?->name,
                'telegram' => $winner->user?->telegram,
                'duration_months' => (int) $winner->duration_months,
                'weight_at_draw' => (int) $winner->weight_at_draw,
            ])->values()->all(),
        ];
    }
}
