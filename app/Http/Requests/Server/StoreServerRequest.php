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
            'link_host' => ['nullable', 'string', 'max:255'],
            'app_path' => ['required', 'string', 'max:255'],
            'ssh_private_key' => ['nullable', 'string'],
            'ssh_public_key' => ['nullable', 'string'],
            'is_vless' => ['nullable', 'boolean'],
        ];
    }

    public function toDto(): ServerData
    {
        $data = $this->validated();

        return new ServerData(
            name: $data['name'],
            code: $data['code'],
            ip: $data['ip'],
            linkHost: $data['link_host'] ?? null,
            appPath: $data['app_path'],
            sshPrivateKey: $data['ssh_private_key'] ?? null,
            sshPublicKey: $data['ssh_public_key'] ?? null,
            isVless: (bool) ($data['is_vless'] ?? false),
        );
    }
}
