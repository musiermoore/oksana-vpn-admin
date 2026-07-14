<?php

namespace App\Http\Requests\VlessExternalSubscription;

use App\Models\VlessExternalSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVlessExternalSubscriptionRequest extends FormRequest
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
            'source_url' => ['required', 'string'],
            'filter_pattern' => ['nullable', 'string', 'max:255'],
            'connect_name_prefix' => ['nullable', 'string', 'max:255'],
            'include_in_main_subscription' => ['required', 'boolean'],
            'include_in_whitelist' => ['required', 'boolean'],
            'is_free' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'is_ready' => ['required', 'boolean'],
        ];
    }
}
