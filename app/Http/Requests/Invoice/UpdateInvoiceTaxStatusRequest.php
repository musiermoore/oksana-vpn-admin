<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use App\DTOs\Invoice\UpdateInvoiceTaxStatusData;
use App\Http\Requests\DataFormRequest;
use App\Models\Invoice;
use Illuminate\Validation\Rule;

class UpdateInvoiceTaxStatusRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tax_status' => ['required', 'string', Rule::in(Invoice::taxStatuses())],
        ];
    }

    protected function dtoClass(): string
    {
        return UpdateInvoiceTaxStatusData::class;
    }
}
