<x-layout title="Редактирование сервера">
    <h1>Редактирование сервера</h1>
    <form action="{{ route('servers.update', $server->id) }}" method="POST">
        @csrf
        @method('PATCH')

        <div class="form-group">
            <label for="name">Имя</label>
            <input name="name" id="name" class="form-control" required value="{{ old('name', $server->name) }}">
        </div>
        <div class="form-group">
            <label for="code">Сокращение</label>
            <input name="code" id="code" class="form-control" required value="{{ old('code', $server->code) }}">
        </div>
        <div class="form-group">
            <label for="ip">IP</label>
            <input name="ip" id="ip" class="form-control" required value="{{ old('ip', $server->ip) }}">
        </div>
        <div class="form-group">
            <label for="link_host">Link Host</label>
            <input name="link_host" id="link_host" class="form-control" value="{{ old('link_host', $server->link_host) }}">
        </div>
        <div class="form-group">
            <label for="app_path">Путь до приложения</label>
            <input name="app_path" id="app_path" class="form-control" required value="{{ old('app_path', $server->app_path) }}">
        </div>
        <div class="form-group">
            <label for="description">SSH Private Key</label>
            <textarea name="ssh_private_key" id="ssh_private_key" class="form-control">{{ old('ssh_private_key') }}</textarea>
            <small>* Скрыт. Для обновления, добавьте приватный ключ</small>
        </div>
        <div class="form-group">
            <label for="description">SSH Public Key</label>
            <textarea name="ssh_public_key" id="ssh_public_key" class="form-control">{{ old('ssh_public_key', $server->ssh_public_key) }}</textarea>
            <small>* Должен быть добавлен на сервере</small>
        </div>
        <div class="form-group">
            <input type="hidden" name="is_vless" class="form-control" value="0">
            <input type="checkbox" name="is_vless" id="is_vless" class="" value="1" @checked(old('is_vless', $server->is_vless))>
            <label for="is_vless">Is Vless</label>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
</x-layout>
