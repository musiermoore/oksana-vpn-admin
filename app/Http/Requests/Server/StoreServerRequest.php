<?php

namespace App\Http\Requests\Server;

use App\DTOs\Server\ServerData;
use Illuminate\Foundation\Http\FormRequest;

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
            'is_https' => ['nullable', 'boolean'],
            'link_host' => ['nullable', 'string', 'max:255'],
            'panel_link' => ['nullable', 'string', 'max:255'],
            'panel_username' => ['nullable', 'string', 'max:255'],
            'panel_password' => ['nullable', 'string', 'max:255'],
            'app_path' => ['required', 'string', 'max:255'],
            'ssh_private_key' => ['nullable', 'string'],
            'ssh_public_key' => ['nullable', 'string'],
            'is_vless' => ['nullable', 'boolean'],
            'is_ready' => ['nullable', 'boolean'],
        ];
    }

    public function toDto(): ServerData
    {
        $data = $this->validated();

        return new ServerData(
            name: $data['name'],
            code: $data['code'],
            ip: $data['ip'],
            isHttps: (bool) ($data['is_https'] ?? false),
            linkHost: $data['link_host'] ?? null,
            panelLink: $data['panel_link'] ?? null,
            panelUsername: $data['panel_username'] ?? null,
            panelPassword: $data['panel_password'] ?? null,
            appPath: $data['app_path'],
            sshPrivateKey: $data['ssh_private_key'] ?? null,
            sshPublicKey: $data['ssh_public_key'] ?? null,
            isVless: (bool) ($data['is_vless'] ?? false),
            isReady: (bool) ($data['is_ready'] ?? false),
        );
    }
}
