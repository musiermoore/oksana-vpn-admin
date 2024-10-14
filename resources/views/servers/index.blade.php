<x-layout title="Серверы">
    <div class="d-flex justify-content-between mb-3">
        <h1>Серверы</h1>
        <div>
            <a href="{{ route('servers.create') }}" class="btn btn-primary">Создать</a>
        </div>
    </div>
    <table class="table">
        <thead>
        <tr>
            <th>Имя</th>
            <th>Сокращение</th>
            <th>IP</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($servers as $server)
            <tr>
                <td>{{ $server->name }}</td>
                <td>{{ $server->code }}</td>
                <td>{{ $server->ip }}</td>
                <td>
                    <a href="{{ route('servers.edit', $server->id) }}" class="btn btn-warning btn-sm"><i class="fa-solid fa-pen-to-square"></i></a>
                    <form action="{{ route('servers.destroy', $server->id) }}" method="POST" style="display:inline-block;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm js-remove_confirmation">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</x-layout>
