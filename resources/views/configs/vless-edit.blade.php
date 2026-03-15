<x-layout title="Редактирование конфига">
    <h1>Редактирование конфига</h1>
    <form action="{{ route('vless-configs.update', $config->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label for="server_id">Cервер</label>
            <input type="text" class="form-control" disabled value="{{ $config->server->name }} ({{ $config->server->ip }})">
        </div>
        <div class="form-group">
            <label for="user_id">Участник</label>
            <select name="user_id" id="user_id" class="form-control" required>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected($config->user_id == $user->id)>
                        {{ $user->telegram }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label>Ссылка</label>
            <textarea class="form-control" readonly>{{ $config->getLink() }}</textarea>
        </div>

        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
</x-layout>
