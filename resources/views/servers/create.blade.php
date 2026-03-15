<x-layout title="Создание сервера">
    <h1>Создание сервера</h1>
    <form action="{{ route('servers.store') }}" method="POST">
        @csrf

        <div class="form-group">
            <label for="name">Имя *</label>
            <input name="name" id="name" class="form-control" required value="{{ old('name') }}">
        </div>
        <div class="form-group">
            <label for="code">Сокращение *</label>
            <input name="code" id="code" class="form-control" required value="{{ old('code') }}">
        </div>
        <div class="form-group">
            <label for="ip">IP *</label>
            <input name="ip" id="ip" class="form-control" required value="{{ old('ip') }}">
        </div>
        <div class="form-group">
            <label for="app_path">Путь до приложения *</label>
            <input name="app_path" id="app_path" class="form-control" required value="{{ old('app_path') }}">
        </div>
        <div class="form-group">
            <label for="description">SSH Private Key</label>
            <textarea name="ssh_private_key" id="ssh_private_key" class="form-control">{{ old('ssh_private_key') }}</textarea>
        </div>
        <div class="form-group">
            <label for="description">SSH Public Key</label>
            <textarea name="ssh_public_key" id="ssh_public_key" class="form-control">{{ old('ssh_public_key') }}</textarea>
        </div>
        <div class="form-group">
            <input type="hidden" name="is_vless" class="form-control" value="0">
            <input type="checkbox" name="is_vless" id="is_vless" class="" value="1" @checked(old('is_vless'))>
            <label for="is_vless">Is Vless</label>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
</x-layout>
