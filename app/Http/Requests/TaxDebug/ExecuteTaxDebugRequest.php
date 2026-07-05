<?php

declare(strict_types=1);

namespace App\Http\Requests\TaxDebug;

use App\DTOs\Tax\TaxDebugRequestData;
use App\Http\Requests\DataFormRequest;
use Illuminate\Validation\Validator;

class ExecuteTaxDebugRequest extends DataFormRequest
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
            'preset' => ['required', 'string', 'in:auth,user,income'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((string) $this->input('preset') === 'income' && blank($this->input('invoice_id'))) {
                $validator->errors()->add('invoice_id', 'Для Income / Receipts выберите invoice.');
            }
        });
    }

    protected function dtoClass(): string
    {
        return TaxDebugRequestData::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function additionalDtoData(): array
    {
        return [
            'userId' => $this->user()?->id,
        ];
    }
}
