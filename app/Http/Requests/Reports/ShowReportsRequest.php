<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\DTOs\Reports\ReportsFilterData;
use App\Http\Requests\DataFormRequest;

class ShowReportsRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    protected function additionalDtoData(): array
    {
        return [
            'dateFrom' => $this->validated('date_from'),
            'dateTo' => $this->validated('date_to'),
        ];
    }

    protected function dtoClass(): string
    {
        return ReportsFilterData::class;
    }
}
