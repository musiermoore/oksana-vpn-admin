<x-layout title="Создать конфиги">
    <h1>Создать конфиги</h1>

    <div class="alert alert-info">
        Конфиги будут созданы, если у пользователя нет ни одного конфига на этом сервере.
    </div>

    <form action="{{ route('configs.store-bulk') }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="server_id">Cервер</label>
            <select name="server_id" id="server_id" class="form-control" required>
                @foreach ($servers as $server)
                    <option value="{{ $server->id }}">{{ $server->name }} ({{ $server->ip }})</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
</x-layout>
