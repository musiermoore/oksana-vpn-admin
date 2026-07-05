<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Spatie\LaravelData\Data;

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

    public function toDto(): Data
    {
        $dtoClass = $this->dtoClass();

        return $dtoClass::from($this->dtoPayload());
    }
}
