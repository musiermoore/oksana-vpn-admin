<x-layout title="Создание сервера">
    <h1>Создание сервера</h1>
    <form action="{{ route('servers.store') }}" method="POST">
        @csrf

        <div class="form-group">
            <label for="name">Имя</label>
            <input name="name" id="name" class="form-control" required value="{{ old('name') }}">
        </div>
        <div class="form-group">
            <label for="code">Сокращение</label>
            <input name="code" id="code" class="form-control" required value="{{ old('code') }}">
        </div>
        <div class="form-group">
            <label for="ip">IP</label>
            <input name="ip" id="ip" class="form-control" required value="{{ old('ip') }}">
        </div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
</x-layout>
