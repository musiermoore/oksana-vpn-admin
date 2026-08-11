<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Giveaway;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GiveawayResource extends JsonResource
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
            'admins_only' => (bool) $giveaway->admins_only,
            'status' => $giveaway->status,
            'status_label' => $giveaway->formattedStatus(),
            'starts_at' => optional($giveaway->starts_at)?->toAtomString(),
            'ends_at' => optional($giveaway->ends_at)?->toAtomString(),
            'duration_minutes' => (int) $giveaway->duration_minutes,
            'duration_days' => $giveaway->durationDays(),
            'sequence_number' => (int) $giveaway->sequence_number,
            'series' => $giveaway->series ? [
                'id' => $giveaway->series->id,
                'name' => $giveaway->series->name,
                'auto_repeat_enabled' => (bool) $giveaway->series->auto_repeat_enabled,
                'repeat_delay_minutes' => (int) $giveaway->series->repeat_delay_minutes,
                'repeat_limit' => $giveaway->series->repeat_limit !== null ? (int) $giveaway->series->repeat_limit : null,
            ] : null,
            'prizes' => $giveaway->prizes->map(fn ($prize) => [
                'id' => $prize->id,
                'duration_months' => (int) $prize->duration_months,
                'quantity' => (int) $prize->quantity,
                'title' => $prize->resolvedTitle(),
                'sort_order' => (int) $prize->sort_order,
            ])->values()->all(),
            'stats' => [
                'participants_count' => $giveaway->participants->count(),
                'eligible_referrals_count' => (int) $giveaway->participants->sum(fn ($participant) => (int) ($participant->eligible_referrals_count_at_draw ?? 0)),
                'total_weight' => (int) $giveaway->participants->sum(fn ($participant) => (int) ($participant->weight_at_draw ?? 0)),
                'prizes_count' => (int) $giveaway->prizes->sum('quantity'),
                'winners_count' => $giveaway->winners->count(),
            ],
            'participants' => $giveaway->participants->map(fn ($participant) => [
                'id' => $participant->id,
                'user_id' => $participant->user_id,
                'name' => $participant->user?->name,
                'telegram' => $participant->user?->telegram,
                'joined_at' => optional($participant->joined_at)?->toAtomString(),
                'weight_at_draw' => $participant->weight_at_draw,
                'eligible_referrals_count_at_draw' => $participant->eligible_referrals_count_at_draw,
            ])->values()->all(),
            'winners' => $giveaway->winners->map(fn ($winner) => [
                'id' => $winner->id,
                'user_id' => $winner->user_id,
                'name' => $winner->user?->name,
                'telegram' => $winner->user?->telegram,
                'duration_months' => (int) $winner->duration_months,
                'weight_at_draw' => (int) $winner->weight_at_draw,
                'eligible_referrals_count_at_draw' => (int) $winner->eligible_referrals_count_at_draw,
                'prize_status' => $winner->prize_status,
                'prize_error' => $winner->prize_error,
                'selected_at' => optional($winner->selected_at)?->toAtomString(),
            ])->values()->all(),
            'links' => [
                'edit' => route('giveaways.edit', $giveaway),
                'activate' => route('giveaways.activate', $giveaway),
                'draw' => route('giveaways.draw', $giveaway),
                'cancel' => route('giveaways.cancel', $giveaway),
            ],
        ];
    }
}
