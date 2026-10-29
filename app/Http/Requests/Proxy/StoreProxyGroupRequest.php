<?php

declare(strict_types=1);

namespace App\Http\Requests\Proxy;

use App\DTOs\Proxy\ProxyGroupData;
use App\Http\Requests\DataFormRequest;
use Illuminate\Validation\Rule;

class StoreProxyGroupRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        $groupId = $this->route('proxyGroup')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('proxy_groups', 'name')->ignore($groupId)],
        ];
    }

    protected function laravelData(): string
    {
        return ProxyGroupData::class;
    }
}
