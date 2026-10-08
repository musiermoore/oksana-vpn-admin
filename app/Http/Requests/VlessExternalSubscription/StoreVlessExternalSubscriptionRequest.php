<?php

declare(strict_types=1);

namespace App\Http\Requests\VlessExternalSubscription;

use App\DTOs\VlessExternalSubscription\VlessExternalSubscriptionData;
use App\Enums\ExternalSubscriptionSourceFormat;
use App\Http\Requests\DataFormRequest;
use App\Models\VlessExternalSubscription;
use Illuminate\Validation\Rule;

class StoreVlessExternalSubscriptionRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', Rule::in([
                VlessExternalSubscription::TYPE_SUBSCRIPTION,
                VlessExternalSubscription::TYPE_DIRECT,
            ])],
            'source_format' => ['required', Rule::enum(ExternalSubscriptionSourceFormat::class)],
            'source_url' => ['required', 'string'],
            'filter_pattern' => ['nullable', 'string', 'max:255'],
            'connect_name_prefix' => ['nullable', 'string', 'max:255'],
            'include_in_main_subscription' => ['required', 'boolean'],
            'include_in_whitelist' => ['required', 'boolean'],
            'include_in_connect_v2' => ['required', 'boolean'],
            'is_free' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'is_ready' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source_format' => $this->input('source_format', ExternalSubscriptionSourceFormat::Direct->value),
        ]);
    }

    protected function laravelData(): string
    {
        return VlessExternalSubscriptionData::class;
    }
}
