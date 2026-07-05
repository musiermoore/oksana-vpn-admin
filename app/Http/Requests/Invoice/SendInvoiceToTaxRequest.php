<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use App\DTOs\Invoice\SendInvoiceToTaxData;
use App\Http\Requests\DataFormRequest;

class SendInvoiceToTaxRequest extends DataFormRequest
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
        return [];
    }

    protected function dtoClass(): string
    {
        return SendInvoiceToTaxData::class;
    }
}
