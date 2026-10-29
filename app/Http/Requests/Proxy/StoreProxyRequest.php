<?php

declare(strict_types=1);

namespace App\Http\Requests\Proxy;

use App\DTOs\Proxy\ProxyData;
use App\Http\Requests\DataFormRequest;
use Illuminate\Validation\Rule;

class StoreProxyRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'hosts' => ['required', 'array', 'min:1'],
            'hosts.*.id' => ['nullable', 'integer', Rule::exists('proxy_hosts', 'id')],
            'hosts.*.host' => ['required', 'string', 'max:255'],
            'hosts.*.port' => ['required', 'integer', 'between:1,65535'],
            'hosts.*.sort' => ['required', 'integer', 'min:0'],
            'hosts.*.is_active' => ['required', 'boolean'],
            'hosts.*.is_https' => ['nullable', 'boolean'],
            'hosts.*.proxy_server_id' => ['nullable', 'integer', Rule::exists('servers', 'id')],
            'server_id' => ['required', 'integer', Rule::exists('servers', 'id')],
            'proxy_group_id' => ['nullable', 'integer', Rule::exists('proxy_groups', 'id')],
            'inbound_id' => ['nullable', 'integer', 'min:1'],
            'is_https' => ['required', 'boolean'],
            'is_ready' => ['required', 'boolean'],
            'hide_main_node_name' => ['required', 'boolean'],
            'description' => ['nullable', 'string'],
        ];
    }

    protected function laravelData(): string
    {
        return ProxyData::class;
    }

    public function after(): array
    {
        return [function ($validator): void {
            $hosts = (array) $this->input('hosts', []);
            uasort($hosts, static fn (mixed $left, mixed $right): int =>
                ((int) (is_array($left) ? ($left['sort'] ?? 0) : 0)) <=> ((int) (is_array($right) ? ($right['sort'] ?? 0) : 0))
            );
            foreach ($hosts as $index => $host) {
                if (is_array($host) && ! empty($host['is_active'])) {
                    if (! empty($host['proxy_server_id'])) {
                        $validator->errors()->add("hosts.{$index}.proxy_server_id", 'The entrypoint cannot have a proxy server.');
                    }

                    break;
                }
            }
        }];
    }
}
