<?php

namespace App\Http\Requests\Server;

use App\DTOs\Server\ServerData;
use App\Models\Server;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServerRequest extends FormRequest
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

    public function toDto(): ServerData
    {
        $data = $this->validated();

        return new ServerData(
            name: $data['name'],
            code: $data['code'],
            ip: $data['ip'],
            type: (string) $data['type'],
            isHttps: (bool) ($data['is_https'] ?? false),
            linkHost: $data['link_host'] ?? null,
            panelLink: $data['panel_link'] ?? null,
            panelUsername: $data['panel_username'] ?? null,
            panelPassword: $data['panel_password'] ?? null,
            panelApiVersion: $data['panel_api_version'] ?? Server::PANEL_API_V2_9,
            appPath: $data['app_path'],
            sshPrivateKey: $data['ssh_private_key'] ?? null,
            sshPublicKey: $data['ssh_public_key'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
            isReady: (bool) ($data['is_ready'] ?? false),
            hideConfigsForNonAdmins: (bool) ($data['hide_configs_for_non_admins'] ?? false),
            allowedInboundIds: $data['type'] === Server::TYPE_VLESS
                ? $this->normalizeAllowedInboundIds($data['allowed_inbound_ids'] ?? null)
                : null,
        );
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
}
