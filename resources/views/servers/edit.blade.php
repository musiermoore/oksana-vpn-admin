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
            <label for="app_path">Путь до приложения</label>
            <input name="app_path" id="app_path" class="form-control" required value="{{ old('app_path', $server->app_path) }}">
        </div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
</x-layout>
