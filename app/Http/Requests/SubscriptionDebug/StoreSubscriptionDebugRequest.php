<?php

declare(strict_types=1);

namespace App\Http\Requests\SubscriptionDebug;

use App\DTOs\SubscriptionDebug\StoreSubscriptionDebugData;
use App\Http\Requests\DataFormRequest;

final class StoreSubscriptionDebugRequest extends DataFormRequest
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
            'type' => ['required', 'string', 'in:json,url'],
            'body' => ['required', 'string'],
        ];
    }

    protected function laravelData(): string
    {
        return StoreSubscriptionDebugData::class;
    }
}
