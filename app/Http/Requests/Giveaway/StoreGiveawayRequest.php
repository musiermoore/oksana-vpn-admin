<?php

declare(strict_types=1);

namespace App\Http\Requests\Giveaway;

use App\DTOs\Giveaway\GiveawayData;
use App\DTOs\Giveaway\GiveawayPrizeData;
use App\Http\Requests\DataFormRequest;
use App\Models\GiveawayPrize;
use Illuminate\Validation\Rule;

class StoreGiveawayRequest extends DataFormRequest
{
    protected function prepareForValidation(): void
    {
        $normalizedStartsAt = $this->normalizeClientDateTime($this->input('starts_at'));
        $normalizedEndsAt = $this->normalizeClientDateTime($this->input('ends_at'));

        $this->merge([
            'starts_at' => $normalizedStartsAt ?? $this->input('starts_at'),
            'ends_at' => $normalizedEndsAt ?? $this->input('ends_at'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'client_timezone' => ['nullable', 'string', 'max:255'],
            'auto_repeat_enabled' => ['required', 'boolean'],
            'repeat_delay_minutes' => ['required', 'integer', 'min:0'],
            'repeat_limit' => ['nullable', 'integer', 'min:1'],
            'prizes' => ['required', 'array', 'min:1'],
            'prizes.*.duration_months' => ['required', 'integer', Rule::in(GiveawayPrize::ALLOWED_DURATION_MONTHS)],
            'prizes.*.quantity' => ['required', 'integer', 'min:0'],
            'prizes.*.title' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function additionalDtoData(): array
    {
        return [
            'startsAt' => $this->validated('starts_at'),
            'endsAt' => $this->validated('ends_at'),
            'autoRepeatEnabled' => (bool) $this->validated('auto_repeat_enabled'),
            'repeatDelayMinutes' => (int) $this->validated('repeat_delay_minutes'),
            'repeatLimit' => $this->filled('repeat_limit') ? (int) $this->validated('repeat_limit') : null,
            'prizes' => array_values(array_map(
                fn (array $prize, int $index): GiveawayPrizeData => new GiveawayPrizeData(
                    durationMonths: (int) $prize['duration_months'],
                    quantity: (int) $prize['quantity'],
                    title: $prize['title'] ?? null,
                    sortOrder: $index,
                ),
                $this->validated('prizes'),
                array_keys($this->validated('prizes')),
            )),
        ];
    }

    protected function dtoClass(): string
    {
        return GiveawayData::class;
    }
}
