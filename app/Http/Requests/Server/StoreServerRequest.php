<?php

declare(strict_types=1);

namespace App\Http\Requests\Server;

use App\DTOs\Server\ServerData;
use App\Http\Requests\DataFormRequest;
use App\Models\Server;
use Illuminate\Validation\Rule;

class StoreServerRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255'],
            'ip' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(Server::allowedTypes())],
            'is_https' => ['nullable', 'boolean'],
            'link_host' => ['nullable', 'string', 'max:255'],
            'panel_link' => ['nullable', 'string', 'max:255'],
            'panel_username' => ['nullable', 'string', 'max:255'],
            'panel_password' => ['nullable', 'string', 'max:255'],
            'panel_api_version' => ['nullable', 'string', Rule::in([
                Server::PANEL_API_V2_9,
                Server::PANEL_API_V3_2_8,
            ])],
            'app_path' => ['required', 'string', 'max:255'],
            'ssh_private_key' => ['nullable', 'string'],
            'ssh_public_key' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_ready' => ['nullable', 'boolean'],
            'hide_configs_for_non_admins' => ['nullable', 'boolean'],
            'allowed_inbound_ids' => ['nullable', 'array'],
            'allowed_inbound_ids.*' => ['integer', 'min:1'],
        ];
    }

    protected function dtoClass(): string
    {
        return ServerData::class;
    }

    private function normalizeAllowedInboundIds(?array $value): ?array
    {
        $inboundIds = collect($value ?? [])
            ->map(fn (mixed $id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        return $inboundIds === [] ? null : $inboundIds;
    }

    /**
     * @return array<string, mixed>
     */
    protected function additionalDtoData(): array
    {
        return [
            'allowedInboundIds' => (string) $this->input('type') === Server::TYPE_VLESS
                ? $this->normalizeAllowedInboundIds($this->validated('allowed_inbound_ids', null))
                : null,
        ];
    }
}
