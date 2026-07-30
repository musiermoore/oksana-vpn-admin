<?php

declare(strict_types=1);

namespace App\Http\Requests\Server;

use App\DTOs\Server\ServerConnectItemSortData;
use App\Http\Requests\DataFormRequest;
use Illuminate\Validation\Rule;

class SortServerConnectItemsRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string', Rule::in(['inbound', 'proxy'])],
            'items.*.id' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function dtoClass(): string
    {
        return ServerConnectItemSortData::class;
    }
}
