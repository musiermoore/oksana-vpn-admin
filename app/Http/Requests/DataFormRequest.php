<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\LaravelData\Data;
use Throwable;

abstract class DataFormRequest extends FormRequest
{
    /**
     * @return class-string<Data>
     */
    abstract protected function dtoClass(): string;

    /**
     * @return array<string, mixed>
     */
    protected function dtoPayload(): array
    {
        return [
            ...$this->validated(),
            ...$this->additionalDtoData(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function additionalDtoData(): array
    {
        return [];
    }

    protected function clientTimezone(): string
    {
        $timezone = trim((string) ($this->input('client_timezone') ?: $this->header('X-Client-Timezone', 'UTC')));

        if ($timezone === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            return 'UTC';
        }

        return $timezone;
    }

    protected function normalizeClientDateTime(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $timezone = $this->clientTimezone();

        try {
            return Carbon::createFromFormat('Y-m-d\TH:i', $value, $timezone)
                ->utc()
                ->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return Carbon::parse($value, $timezone)
                ->utc()
                ->format('Y-m-d H:i:s');
        }
    }

    public function toDto(): Data
    {
        $dtoClass = $this->dtoClass();

        return $dtoClass::from($this->dtoPayload());
    }
}
